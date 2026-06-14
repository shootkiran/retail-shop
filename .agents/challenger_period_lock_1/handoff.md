# Handoff Report: Period Locking Control (R4) Robustness Challenge

## 1. Observation

During my empirical verification of the Period Locking Control (R4) implementation in `app/Models/Accounting/JournalEntry.php` and surrounding transaction flows, I directly observed the following files and behaviors:

### A. Lack of Lifecycle hooks in `JournalLine`
In `app/Models/Accounting/JournalLine.php`, there are no lifecycle observers or event hooks defined to check for the period lock.
```php
class JournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'reference',
        'notes',
    ];
    // No booted() method or event hooks checking App\Models\BusinessSetting
}
```

### B. Query Builder Deletions in Transaction Models
In `app/Models/Sale.php`, `app/Models/Purchase.php`, `app/Models/CustomerPayment.php`, `app/Models/Accounting/VendorBill.php`, and others, the model `deleted` hooks directly run query builder delete queries on the `JournalEntry` table. For example, in `app/Models/Sale.php` (lines 66-77):
```php
        static::deleted(function (Sale $sale): void {
            // Delete journal entry when sale is deleted
            JournalEntry::withoutGlobalScopes()
                ->where('source_type', $sale->getMorphClass())
                ->where('source_id', $sale->getKey())
                ->delete();

            // Also delete COGS entry
            JournalEntry::withoutGlobalScopes()
                ->where('reference', $sale->reference.'-COGS')
                ->delete();
        });
```
Because this utilizes direct database query builder calls (`delete()`), it bypasses Eloquent's model event system entirely.

### C. Tinker Test Results
When running the verification scripts in Laravel Tinker, I observed:
1. **JournalLine Modification**:
   Setting the lock date to `2026-06-01` and attempting to update a journal entry on `2026-05-15` throws the expected `RuntimeException`. However, updating the debit amount of its child `JournalLine` from `100.00` to `200.00`, deleting a `JournalLine`, or inserting a new `JournalLine` all succeeded without throwing any exception:
   ```
   PASS (Locked): This transaction falls within a locked fiscal period (Lock Date: 2026-06-01). Modifications are blocked.
   BYPASS 1 SUCCESS: Updated JournalLine debit to 200 on locked JournalEntry! New value: 200.00
   BYPASS 2 SUCCESS: Deleted JournalLine from locked JournalEntry!
   BYPASS 3 SUCCESS: Created new JournalLine on locked JournalEntry!
   ```
2. **Transaction Deletion**:
   Creating a `Sale` dated `2026-05-15` (generating a `JournalEntry`), locking the period (`2026-06-01`), and then calling `$sale->delete()` succeeded, completely deleting the sale and its associated journal entries:
   ```
   SALE DELETE SUCCEEDED!
   Journal entries before: 1, after: 0
   ```

---

## 2. Logic Chain

1. **Child Record Modification Bypass**:
   * **Observation**: `JournalLine.php` does not have any event hooks checking `period_lock_date`.
   * **Inference**: While the parent `JournalEntry` is protected from direct updates/deletion via its `saving` and `deleting` hooks, its financial lines (`JournalLine`) are stored as separate models.
   * **Conclusion**: Users can alter debit/credit amounts, accounts, or delete lines on locked entries. This completely invalidates the integrity of the locked period since the ledger balances and account distributions can still be modified.

2. **Transaction Document Deletion Bypass**:
   * **Observation**: High-level transaction models (`Sale`, `Purchase`, etc.) do not check `period_lock_date` before deletion. Their `deleted` hook uses direct query builder `delete()` statements on the `JournalEntry` table.
   * **Inference**: Query builder operations (`delete()`, `update()`, `insert()`) bypass Eloquent event dispatcher hooks.
   * **Conclusion**: Any backoffice user can delete sales, purchases, or customer payments in a locked period, and the system will silently delete their corresponding journal entries, bypassing the R4 lock entirely.

3. **Timezone Date Shifting Inconsistency**:
   * **Observation**: `JournalEntry` compares dates naively using `Carbon::parse()` in the application timezone (`Asia/Kathmandu`), while businesses have their own `timezone` configuration (e.g. `America/New_York`).
   * **Inference**: Date-time values sent with client timezone offsets are cast using the application default timezone in Eloquent.
   * **Conclusion**: A transaction recorded at a time that falls on the locked date in New York local time may get shifted to the next day (unlocked) in the application timezone, allowing it to bypass validation and get stored in the database.

---

## 3. Caveats

* **Concurrency**: Did not test concurrency race conditions under high transaction loads (e.g., SQLite lock serialization vs MySQL row level locking).
* **Environment**: Assumed default Laravel database connection configuration.
* **Manual DB Operations**: Direct access to the database (via MySQL CLI, SQLite CLI, etc.) naturally bypasses all application code checks (standard behavior).

---

## 4. Conclusion & Challenge Report

**Overall risk assessment**: CRITICAL

### Challenges & Bypasses Identified

#### [Critical] Challenge 1: `JournalLine` Modification Bypass
* **Assumption challenged**: Locking `JournalEntry` locks the entire double-entry transaction.
* **Attack scenario**: An accountant wishes to alter the financial report of a locked period. They load a `JournalLine` belonging to a locked entry and change its `debit` or `credit` value directly, or delete it.
* **Blast radius**: Alters financial integrity, causes unbalanced entries, and changes ledger balances in locked periods.
* **Mitigation**: Add saving and deleting event hooks to `JournalLine` that check the parent `JournalEntry`'s `entry_date` against the business's `period_lock_date`.

#### [Critical] Challenge 2: Transaction Document Deletion Bypass
* **Assumption challenged**: Deleting high-level transactions is prevented if they lie within the locked period.
* **Attack scenario**: A user deletes a `Sale` or `Purchase` dated inside the locked period. Since the models use query builder to delete `JournalEntry` rows, the lock checks are bypassed, and both the document and the journal entries are deleted.
* **Blast radius**: Complete deletion of ledger transactions from a locked period.
* **Mitigation**: Add period locking checks to the `deleting` hooks of all transaction models (`Sale`, `Purchase`, `CustomerPayment`, `VendorBill`, `VendorBillPayment`, `CreditNote`).

#### [Medium] Challenge 3: Query Builder Bulk Update Bypass
* **Assumption challenged**: All database updates to `JournalEntry` will trigger the period lock check.
* **Attack scenario**: A script or controller updates journal entry dates or descriptions using `JournalEntry::where(...)->update(...)`.
* **Blast radius**: Allows modifying locked entries.
* **Mitigation**: Standard Laravel limitation, but developers should be warned not to use bulk update query builder calls for journal entries, or execute checks manually in services/repositories.

#### [Low/Medium] Challenge 4: Timezone Date Shifting
* **Assumption challenged**: Date parsing is timezone-safe.
* **Attack scenario**: A user posts an entry with a timezone offset that shifts the day when converted to the application timezone.
* **Blast radius**: Posting locked-period entries into the database.
* **Mitigation**: Parse dates explicitly using the tenant business timezone settings.

---

## 5. Verification Method

### A. Unit / Feature Tests
All project tests pass successfully, confirming that the basic implementation behaves as expected on the surface:
```bash
vendor/bin/phpunit
```

### B. Empirical Replication (Laravel Tinker)

Run the following command to verify the **`JournalLine` bypass**:
```php
php artisan tinker --execute="
\$b = App\Models\Business::first();
\$s = App\Models\BusinessSetting::withoutGlobalScopes()->where('business_id', \$b->id)->first();
\$s->update(['period_lock_date' => null]);
\$entry = App\Models\Accounting\JournalEntry::create(['business_id' => \$b->id, 'entry_date' => '2026-05-15', 'reference' => 'LOCKED-TEST']);
\$acc1 = App\Models\Accounting\Account::first();
\$line = App\Models\Accounting\JournalLine::create(['journal_entry_id' => \$entry->id, 'account_id' => \$acc1->id, 'debit' => 100.00]);
\$s->update(['period_lock_date' => '2026-06-01']);
try {
    \$line->update(['debit' => 200.00]);
    echo 'BYPASS SUCCESS: Updated line debit to ' . \$line->fresh()->debit . PHP_EOL;
} catch (\Exception \$e) {
    echo 'BLOCKED: ' . \$e->getMessage() . PHP_EOL;
}
\$entry->forceDelete();
"
```

Run the following command to verify the **`Sale` deletion bypass**:
```php
php artisan tinker --execute="
\$b = App\Models\Business::first();
\$s = App\Models\BusinessSetting::withoutGlobalScopes()->where('business_id', \$b->id)->first();
\$s->update(['period_lock_date' => null]);
\$sale = App\Models\Sale::create(['business_id' => \$b->id, 'customer_id' => 1, 'payment_method_id' => 4, 'sold_at' => '2026-05-15', 'total_amount' => 500, 'grand_total' => 500, 'amount_paid' => 500, 'amount_due' => 0, 'status' => 'completed', 'payment_status' => 'paid']);
\$item = App\Models\SaleItem::create(['sale_id' => \$sale->id, 'product_item_id' => 1007, 'quantity' => 1, 'unit_price' => 500, 'total_price' => 500]);
\$sale->refresh();
\$sale->syncJournalEntry();
\$s->update(['period_lock_date' => '2026-06-01']);
try {
    \$sale->delete();
    echo 'BYPASS SUCCESS: Deleted sale in locked period!' . PHP_EOL;
} catch (\Exception \$e) {
    echo 'BLOCKED: ' . \$e->getMessage() . PHP_EOL;
}
"
```

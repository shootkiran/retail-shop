# Period Lock Bypass & Robustness Challenge Report

## 1. Observation
The following files and logic were analyzed for Period Locking Control (R4) robustness:
- **`app/Models/Accounting/JournalEntry.php`**: Implements `checkPeriodLock()` inside the Eloquent `saving` and `deleting` hooks.
- **`app/Models/Accounting/JournalLine.php`**: Implements parent lock checking in its `saving` and `deleting` hooks:
  ```php
  static::saving(function (JournalLine $line): void {
      if ($line->isDirty('journal_entry_id')) {
          $originalEntryId = $line->getOriginal('journal_entry_id');
          if ($originalEntryId) {
              $originalEntry = JournalEntry::find($originalEntryId);
              if ($originalEntry) {
                  $originalEntry->checkPeriodLock();
              }
          }
      }
      if ($line->entry) {
          $line->entry->checkPeriodLock();
      }
  });
  ```
- **`app/Models/Sale.php`** (and similarly in `Purchase`, `VendorBill`, `VendorBillPayment`, `CreditNote`, `CustomerPayment`): Implements the `checkTransactionPeriodLock($dateField, ...)` logic on `saving` and `deleting`.
- **`app/Models/SaleItem.php`** (and similarly in `PurchaseItem`, `VendorBillItem`, `CreditNoteItem`): Does not implement any direct period lock check, but hooks into `saved` or `created` to call parent's `refreshTotals()`, which syncs the journal entry via `JournalEntryService::createEntry()`.

A custom PHPUnit test suite (`tests/Feature/PeriodLockBypassTest.php`) was created and executed using:
`vendor/bin/phpunit tests/Feature/PeriodLockBypassTest.php`

The tests succeeded, demonstrating the following bypass and consistency behaviors:
1. **JournalLine moving bypass**: Successfully updated `journal_entry_id` of a `JournalLine` from an unlocked entry to a locked entry without triggering any exception. The database update succeeded, violating the period lock.
2. **SaleItem moving bypass**: Successfully updated `sale_id` of a `SaleItem` from an unlocked sale to a locked sale without triggering any exception. The database item was relocated, but the parent locked sale totals and journal entries were not updated, violating data integrity.
3. **Missing updating hooks**: Modifying quantity/price on `VendorBillItem` or `CreditNoteItem` did not update parent `VendorBill` or `CreditNote` totals, leaving the database inconsistent.

---

## 2. Logic Chain

### Overall Risk Assessment: HIGH

### Challenge 1: [Critical] Eloquent Relationship Caching Lock Bypass on JournalLine
- **Assumption Challenged**: That accessing `$line->entry` in `JournalLine::saving` always checks the new parent entry to which the line is being moved.
- **Attack Scenario**:
  1. A user creates a `JournalLine` under an unlocked `JournalEntry B` (e.g. date: `2026-06-15`).
  2. Accessing the line or saving it caches the relationship `entry` pointing to the unlocked `JournalEntry B`.
  3. The user updates `journal_entry_id` of that line to point to a locked `JournalEntry A` (e.g. date: `2026-05-15`).
  4. In the `saving` hook of `JournalLine`, `$line->entry` resolves to the cached unlocked `JournalEntry B`.
  5. `$line->entry->checkPeriodLock()` evaluates `JournalEntry B` (unlocked) and does not throw.
  6. The database is updated, moving the `JournalLine` to the locked `JournalEntry A` without any period lock exception.
- **Blast Radius**: Financial ledger integrity. Transactions can be altered by inserting/deleting/moving journal lines in a locked fiscal period, making financial reports unreliable.
- **Mitigation**: In `JournalLine.php`, if `journal_entry_id` is dirty, check the new parent entry resolved fresh via `JournalEntry::find($line->journal_entry_id)` or clear the relation cache using `$line->unsetRelation('entry')` before checking.
  ```php
  if ($line->isDirty('journal_entry_id')) {
      $newEntry = JournalEntry::find($line->journal_entry_id);
      if ($newEntry) {
          $newEntry->checkPeriodLock();
      }
  }
  ```

### Challenge 2: [Critical] Eloquent Relationship Caching Lock Bypass on SaleItem / PurchaseItem
- **Assumption Challenged**: That moving an item to a locked parent transaction triggers a journal entry sync that fails due to period locking.
- **Attack Scenario**:
  1. Create a `SaleItem` under an unlocked `Sale B`. Saving the item caches `$item->sale` pointing to `Sale B` (unlocked).
  2. Update the item's `sale_id` to point to locked `Sale A`.
  3. In `SaleItem`'s `saved` hook, it calls `$item->sale?->refreshTotals()`.
  4. Because of relationship caching, it resolves to the cached unlocked `Sale B`, refreshing `Sale B`'s totals.
  5. It never refreshes totals or triggers journal entry sync on the locked `Sale A`.
  6. The database updates the item's parent to `Sale A`, but `Sale A`'s totals and journal entries remain out-of-sync and the period lock is completely bypassed.
- **Blast Radius**: Inventory and sales reporting integrity. Items can be surreptitiously moved into/out of sales in locked periods, corrupting stock counts and revenue metrics.
- **Mitigation**: Unset relation caches or query the parent model directly.
  ```php
  static::saved(function (SaleItem $item): void {
      if ($item->isDirty('sale_id')) {
          $originalSaleId = $item->getOriginal('sale_id');
          if ($originalSaleId) {
              $oldSale = Sale::find($originalSaleId);
              $oldSale?->refreshTotals();
          }
          $newSale = Sale::find($item->sale_id);
          $newSale?->refreshTotals();
      } else {
          $item->sale?->refreshTotals();
      }
  });
  ```

### Challenge 3: [High] Database Inconsistency on VendorBill / CreditNote Item Updates
- **Assumption Challenged**: That updating a item updates the parent transaction's total and keeps the general ledger in sync.
- **Attack Scenario**:
  1. Create a `VendorBill` (status: posted) with a `VendorBillItem` of quantity 1 at cost $100. Bill total is $100.
  2. Update `VendorBillItem` quantity to 5.
  3. Since `VendorBillItem` has no `updating`/`saved` event hooks registered, the parent `VendorBill`'s totals remain $100 instead of $500, and the synced journal entry remains $100.
- **Blast Radius**: Subledger and General Ledger mismatches. The items table disagrees with the bills table, which disagrees with the journal entries.
- **Mitigation**: Register the `saved` event hook in `VendorBillItem` and `CreditNoteItem` to call their parent's `refreshTotals()` method, similar to `SaleItem` and `PurchaseItem`.

---

## 3. Caveats
- This review did not check whether mass updates/deletes (e.g. `JournalLine::where(...)->update(...)`) are executed anywhere in the controllers. Eloquent model events do not fire on mass query builder updates, which is a standard Laravel behavior that must be guarded against at the controller/service level.

---

## 4. Conclusion
While the period lock date checks on datetime normalization, timezone offsets, and business ID changes are extremely robust, **critical loopholes exist** when modifying the relationships of child records (`JournalLine`, `SaleItem`, `PurchaseItem`). Because Eloquent caches relationship properties, moving lines/items between unlocked and locked parents bypasses the period lock and introduces severe data inconsistencies. Furthermore, `VendorBillItem` and `CreditNoteItem` completely lack update triggers, leaving their parent records out of sync.

---

## 5. Verification Method

To verify these vulnerabilities independently:
1. Re-create the temporary test file `tests/Feature/PeriodLockBypassTest.php` with the contents documented in progress history.
2. Run the test suite:
   ```bash
   vendor/bin/phpunit tests/Feature/PeriodLockBypassTest.php
   ```
3. Verify that the tests pass successfully, confirming that:
   - Moving a `JournalLine` from an unlocked entry to a locked entry succeeds (`test_moving_journal_line_between_locked_and_unlocked_entries`).
   - Moving a `SaleItem` from an unlocked sale to a locked sale succeeds (`test_moving_sale_item_between_locked_and_unlocked_sales`).
   - Updating `VendorBillItem` or `CreditNoteItem` fails to update the parent totals (`test_vendor_bill_item_and_credit_note_item_update_consistency`).

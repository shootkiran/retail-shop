# Challenge Report: Period Locking Control Robustness Verification

**Overall risk assessment**: CRITICAL

---

## 1. Observations

### Observation A: Eloquent In-Memory State Pollution Bypass
When trying to update a field (e.g., date or parent ID) on a model associated with a locked period/parent, Eloquent triggers the `saving` observer which successfully blocks the write by throwing a `RuntimeException`. However, the model instance in memory retains the mutated (unlocked) value because Laravel does not rollback attributes on failed saves.
- **Parent Models (e.g. `JournalEntry`, `Sale`, `Purchase`, `VendorBill`, `CreditNote`):**
  In `app/Models/Accounting/JournalEntry.php` (line 65):
  ```php
  if ($entryDate->lessThanOrEqualTo($lockDate)) {
      throw new \RuntimeException("This transaction falls within a locked fiscal period...");
  }
  ```
  If this throws, the in-memory value of `$entry->entry_date` remains the unlocked date. Accessing it subsequently in a `delete()` call resolves `$this->entry_date` to the in-memory unlocked date, bypassing the `deleting` check.
- **Child Models (e.g. `JournalLine`, `SaleItem`, `PurchaseItem`, `VendorBillItem`, `CreditNoteItem`):**
  In `app/Models/Accounting/JournalLine.php` (lines 38-59) and other child models:
  Accessing `$line->entry` or other parent relations after an aborted parent-ID update resolves the relation using the polluted in-memory parent ID (the unlocked parent). Thus, the `deleting` hook calls `$line->entry->checkPeriodLock()`, which checks the unlocked entry and succeeds, allowing the deletion of the line item from the locked entry in the database.

**Verification command execution result (reproducible scratch script output):**
```
Attempting to delete locked entry directly...
Success: Blocked direct delete with message: This transaction falls within a locked fiscal period (Lock Date: 2026-06-01). Modifications are blocked.
Attempting to update date to unlocked date (should fail)...
Success: Blocked date update with message: This transaction falls within a locked fiscal period (Lock Date: 2026-06-01). Modifications are blocked.
Attempting to delete the same instance after failed update...
BYPASS DETECTED: Locked entry deleted after failed update!
```
And for `SaleItem` / child item deletion:
```
Attempting to move item from locked sale to unlocked sale...
Success: Blocked moving with message: This transaction falls within a locked fiscal period (Lock Date: 2026-06-01). Modifications are blocked.
Attempting to delete item on locked sale...
BYPASS DETECTED: Item deleted on locked sale!
```

### Observation B: Missing `updating` Hook for Inventory Stock on `VendorBillItem` and `CreditNoteItem`
- In `app/Models/Accounting/VendorBillItem.php` (lines 40-146): There is no `updating` hook defined.
- In `app/Models/Accounting/CreditNoteItem.php` (lines 39-117): There is no `updating` hook defined.
- Unlike `SaleItem` and `PurchaseItem`, which reconcile inventory stock quantities dynamically in `updating` (handling quantity changes and product reassignments), updates to `VendorBillItem` or `CreditNoteItem` quantities do not reconcile inventory stock, leading to silent database-level inventory drifts.

### Observation C: Parent Totals and Journal Entry Synchronization
- `VendorBillItem` and `CreditNoteItem` successfully call `$parent->refreshTotals()` and `$parent->syncJournalEntry()` in their `saved` and `deleted` hooks.
- **`VendorBillItem` (lines 74-119):**
  ```php
  $item->bill->refreshTotals();
  if ($item->bill->status === 'posted' || $item->bill->status === 'paid' || $item->bill->status === 'partially_paid') {
      $item->bill->syncJournalEntry();
  }
  ```
- **`CreditNoteItem` (lines 74-98):**
  ```php
  $item->note->refreshTotals();
  $item->note->syncJournalEntry();
  ```
- All unit and feature tests pass (`tests/Feature/PeriodLockTest.php` and `tests/Unit/SaleTotalsTest.php`).

---

## 2. Logic Chain

1. **Step 1:** In Eloquent, a model's attribute values are updated when `$model->update([...])` or `$model->attribute = ...` is called.
2. **Step 2:** If the model's `saving` event or observer throws a `RuntimeException` (such as a period lock check failure), the database transaction is aborted, but the model instance's in-memory properties are **not** rolled back (Observation A).
3. **Step 3:** If the developer subsequently calls `$model->delete()` or performs another database-writing operation on that same model instance (e.g. in Filament forms, bulk actions, or standard controller lifecycles), the model observers resolve the dates and parent relationships based on the dirty, in-memory attributes (which are now pointing to the unlocked values).
4. **Step 4:** This causes the `deleting` hook to evaluate against the unlocked date or the unlocked parent record, bypassing the period lock check and executing the SQL delete against the database record (which is still locked in the database).
5. **Step 5:** Additionally, updates to `VendorBillItem` and `CreditNoteItem` lack inventory reconciliation hook (`updating` event), meaning stock counts drift when item quantities change (Observation B).
6. **Conclusion:** The current period locking controls are highly vulnerable to bypass when a model instance's parent ID or date is updated and subsequently deleted or modified. The system also permits silent inventory drift on bill/credit note updates.

---

## 3. Caveats

- We assumed that model instances might be reused or deleted during a request lifecycle where an update failed. If model instances are always discarded immediately after an exception is caught, the state pollution bypass would not manifest in that specific execution flow; however, this is a dangerous assumption to rely on across controllers, Filament actions, and queued jobs.
- Direct query builder calls bypass Eloquent observers entirely, which is an expected Laravel limitation and is not evaluated as a logic bug but is noted for completeness.

---

## 4. Conclusion

The period locking implementation is currently **not fully robust** due to:
1. **Critical Vulnerability (State Pollution Lock Bypass):** In-memory attribute pollution permits deleting locked parent transactions and child items if an update to an unlocked parent/date was attempted and rejected first.
2. **High Vulnerability (Inventory Drift):** Lack of stock reconciliation on `VendorBillItem` and `CreditNoteItem` updates leads to incorrect stock counts.

### Recommendations & Mitigations:
- **For parent models (`JournalEntry`, `Sale`, `Purchase`, `VendorBill`, `CreditNote`):**
  Update the period lock check for deletion to retrieve the original database values:
  ```php
  $dateVal = $this->getOriginal($dateField) ?? $this->getAttribute($dateField);
  $businessId = $this->getOriginal('business_id') ?? $this->business_id;
  ```
- **For child models (`JournalLine`, `SaleItem`, `PurchaseItem`, `VendorBillItem`, `CreditNoteItem`):**
  Update the deletion and update hooks to fetch parent IDs using `getOriginal`:
  ```php
  $parentId = $this->getOriginal('parent_id_field') ?? $this->parent_id_field;
  ```
- **For inventory stock updates on Bills/Credit Notes:**
  Implement `updating` hooks on `VendorBillItem` and `CreditNoteItem` to increment/decrement inventory based on the delta, mirroring the logic in `SaleItem` and `PurchaseItem`.

---

## 5. Verification Method

To independently reproduce the in-memory state pollution bypasses, run the following commands in the workspace root:

1. **Verify JournalEntry / Sale Deletion Bypass:**
   Run:
   ```bash
   php -r "
   require 'vendor/autoload.php';
   \$app = require_once 'bootstrap/app.php';
   \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
   // [Setup active business with period lock date 2026-06-01]
   // [Create locked entry on 2026-05-15]
   // [Attempt to update entry_date to 2026-06-15 -> throws RuntimeException]
   // [Call \$entry->delete() -> deletes locked entry successfully]
   "
   ```
2. **Verify Child Item Deletion Bypass:**
   Attempt to update a line item's parent ID from locked to unlocked (fails), and then delete the line item instance (succeeds, deleting the item from the locked parent).
3. **Execute Test Suite:**
   Ensure all existing tests pass:
   ```bash
   vendor/bin/phpunit
   ```

## 2026-06-13T09:47:58Z

Objective: Fix the Eloquent in-memory state pollution lock bypasses and missing inventory updating hooks on VendorBillItem/CreditNoteItem.

Instructions:
1. Update `app/Models/Accounting/JournalEntry.php`:
   - In `checkPeriodLock()`, retrieve `business_id` using `$this->getOriginal('business_id') ?? $this->business_id`.
   - Retrieve the dates using `$this->getOriginal('entry_date')` and `$this->entry_date`.
   - Check if either the original entry date OR the current entry date falls on or before the lock date of the original/current business ID.
2. Update the `checkTransactionPeriodLock()` helper in all core transaction models (`Sale`, `Purchase`, `VendorBill`, `VendorBillPayment`, `CreditNote`, `CustomerPayment`):
   - Make sure the helper retrieves the transaction date using `$this->getOriginal($dateField) ?? $this->$dateField`.
   - Retrieve `business_id` using `$this->getOriginal('business_id') ?? $this->business_id`.
   - Check if the resolved date is on or before the lock date.
3. Update deleting hooks in all child models (`JournalLine`, `SaleItem`, `PurchaseItem`, `VendorBillItem`, `CreditNoteItem`):
   - Ensure that the `deleting` hook retrieves the parent model by querying the database using the original parent relationship ID (obtained via `$this->getOriginal($parentForeignKey) ?? $this->$parentForeignKey`), instead of resolving through the potentially polluted `$this->parentRelation`.
4. Update `app/Models/Accounting/VendorBillItem.php` and `app/Models/Accounting/CreditNoteItem.php`:
   - Implement an `updating` hook in the `booted()` method of both models.
   - Reconcile stock quantities on updates by incrementing/decrementing `stock_quantity` of `ProductItem` based on the delta between the original and new quantity, or handle product item changes (mirroring the logic in `SaleItem` and `PurchaseItem`, but noting that VendorBillItem/CreditNoteItem increment stock on creation instead of decrementing).
5. Update `tests/Feature/PeriodLockTest.php`:
   - Write tests validating that attempting to update a locked record's date to an unlocked date fails, and then trying to delete it in the same context fails.
   - Write tests validating child model deletion bypass is blocked.
   - Write tests validating inventory stock reconciliation on updating `VendorBillItem` or `CreditNoteItem`.
6. Run all phpunit tests and confirm they pass successfully.

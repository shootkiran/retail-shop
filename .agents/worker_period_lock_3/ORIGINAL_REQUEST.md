## 2026-06-13T07:54:18Z

Objective: Address the relationship change loopholes, cached Eloquent parent checks, and database inconsistencies inside child item models for Period Locking (R4).

Instructions:
1. Update `app/Models/Accounting/JournalLine.php`:
   - In `saving`, check if `journal_entry_id` is dirty. If so, retrieve the original entry using `JournalEntry::find($line->getOriginal('journal_entry_id'))` and check its lock, AND retrieve the new entry using `JournalEntry::find($line->journal_entry_id)` and check its lock. Otherwise, check lock on `$line->entry`.
2. Update `app/Models/SaleItem.php`:
   - In `saving`, check the period lock on its parent `Sale` (date field: `sold_at`). If `sale_id` is dirty, retrieve and check both original and new parent sales.
   - In `deleting`, check the period lock on its parent `Sale`.
   - In `saved`, if `sale_id` is dirty, refresh totals for both the original parent sale and the new parent sale.
3. Update `app/Models/PurchaseItem.php`:
   - In `saving`, check the period lock on its parent `Purchase` (date field: `purchased_at`). If `purchase_id` is dirty, retrieve and check both original and new parent purchases.
   - In `deleting`, check the period lock on its parent `Purchase`.
   - In `saved`, if `purchase_id` is dirty, refresh totals for both the original parent purchase and the new parent purchase.
4. Update `app/Models/Accounting/VendorBillItem.php`:
   - In `saving`, check the period lock on its parent `VendorBill` (date field: `bill_date`). If `vendor_bill_id` is dirty, retrieve and check both original and new parent bills.
   - In `deleting`, check the period lock on its parent `VendorBill`.
   - Register a `saved` hook (instead of just `created` / `deleted` hooks):
     - If `vendor_bill_id` is dirty, refresh totals for both the original parent bill and the new parent bill. Otherwise, refresh totals for `$item->bill`.
5. Update `app/Models/Accounting/CreditNoteItem.php`:
   - In `saving`, check the period lock on its parent `CreditNote` (date field: `refunded_at`). If `credit_note_id` is dirty, retrieve and check both original and new parent credit notes.
   - In `deleting`, check the period lock on its parent `CreditNote`.
   - Register a `saved` hook (instead of just `created` / `deleted` hooks):
     - If `credit_note_id` is dirty, refresh totals for both the original parent credit note and the new parent credit note. Otherwise, refresh totals for `$item->note`.
6. Update `tests/Feature/PeriodLockTest.php`:
   - Write tests validating these new behaviors:
     - Check that changing a child item's parent ID (e.g. `journal_entry_id` or `sale_id` or `purchase_id` or `vendor_bill_id` or `credit_note_id`) checks period locks on both old and new parents.
     - Check that editing the quantity/unit price of a `VendorBillItem` or `CreditNoteItem` properly updates the parent totals and syncs journal entries.
7. Run all phpunit tests to verify correctness.

MANDATORY INTEGRITY WARNING:
> DO NOT CHEAT. All implementations must be genuine. DO NOT
> hardcode test results, create dummy/facade implementations, or
> circumvent the intended task. A Forensic Auditor will independently
> verify your work. Integrity violations WILL be detected and your
> work WILL be rejected.

Inputs:
- Working directory: /Users/kiran/Herd/retail-shop/.agents/worker_period_lock_3

Outputs:
- Write a detailed handoff report in /Users/kiran/Herd/retail-shop/.agents/worker_period_lock_3/handoff.md.
- Message the caller (orchestrator) with the absolute path when complete.

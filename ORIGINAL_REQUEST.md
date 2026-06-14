# Original User Request

## 2026-06-13T07:36:47Z

Upgrade the Simple Retail POS and Accounting system to be production-ready by implementing all missing advanced accounting and business operations features.

Working directory: /Users/kiran/Herd/retail-shop
Integrity mode: development

## Requirements

### R1. Accrual Accounts Payable (Vendor Bills)
- Implement `vendor_bills` and `vendor_bill_items` models to book supplier invoices (accrual accounting) with due dates, reference, and total totals.
- Implement a `vendor_bill_payments` model to track payments against bills.
- Automate double-entry: posting a bill debits Inventory/Tax and credits Accounts Payable; paying a bill debits Accounts Payable and credits Bank/Cash.
- Add a Filament `VendorBillResource` allowing bill management and recording payments.

### R2. Credit Notes & Customer Statement PDFs
- Implement customer `credit_notes` and `credit_note_items` to record returns and refunds.
- Automate double-entry: debits Sales Returns and Tax, credits Accounts Receivable; restocks inventory (debit Inventory, credit COGS).
- Implement a route to generate customer statements as a formatted PDF over custom date ranges, sorting invoices, payments, and credit notes chronologically with a running balance.
- Add a "Statement" button to the customer list page to configure date range and download the PDF.

### R3. Advanced Taxation & VAT Report Page
- Create a Filament page `TaxReport` in the Accounting group.
- Display taxable sales & tax output collected, taxable purchases & tax input paid, and net tax payable/refundable.

### R4. Period Locking Control
- Add a `period_lock_date` column in business settings.
- Enforce the lock date inside the journal entry lifecycle: throw a `RuntimeException` preventing creation, edit, or deletion of journal entries on or before the lock date.

### R5. Fixed Assets & Depreciation Register
- Create a `fixed_assets` model to track capitalized assets (purchase date, cost, salvage value, useful life).
- Create a Filament `FixedAssetResource` to manage assets.
- Implement a console command `accounting:run-depreciation` that runs monthly depreciation: debit Depreciation Expense (`5120`), credit Accumulated Depreciation (`1220`).

## Acceptance Criteria

### Accounts Payable & Credit Notes
- [ ] Creation of a Vendor Bill posts a balanced journal entry.
- [ ] Recording a bill payment posts a balanced journal entry and updates the bill balance.
- [ ] Creation of a Credit Note posts a balanced journal entry and restocks inventory.

### Customer Statement & Tax Reports
- [ ] Customer statement PDF generates without errors, computing correct opening, activity, and closing balances.
- [ ] Tax Report Filament page calculates and displays net tax payable/refundable.

### Integrity & Safety Controls
- [ ] Back-dated journal entry edits or deletions before the lock date are blocked and throw a `RuntimeException`.
- [ ] Running `accounting:run-depreciation` calculates correct straight-line depreciation and posts balanced journal entries.
- [ ] All existing and new phpunit tests pass successfully.

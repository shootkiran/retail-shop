# Accounts Payable (Vendor Bills and Payments) Investigation Report

This report documents the structure, database schema, Filament resource implementation, and journal entry posting correctness for Vendor Bills and Vendor Bill Payments in the application.

---

## 1. File Locations (Models, Observers, Migrations, and Filament Resources)

### Models
- **`VendorBill`**: `app/Models/Accounting/VendorBill.php`
  Represents a vendor bill transaction.
- **`VendorBillItem`**: `app/Models/Accounting/VendorBillItem.php`
  Represents individual line items in a vendor bill.
- **`VendorBillPayment`**: `app/Models/Accounting/VendorBillPayment.php`
  Represents payments made against a vendor bill.

### Observers / Event Handlers
There are no standalone Observer classes (e.g. under `app/Observers/`). Instead, Model boot callbacks (`booted()` method) are used to handle business logic events (e.g., updating stock quantities, recalculating bill totals, enforcing period locks, and syncing journal entries).
- **`VendorBill` boot callbacks**: Handles `saving`, `deleting`, `creating`, `saved`, and `deleted` events.
- **`VendorBillItem` boot callbacks**: Handles `saving`, `created`, `updating`, `saved`, `deleting`, and `deleted` events.
- **`VendorBillPayment` boot callbacks**: Handles `saving`, `deleting`, `creating`, `created`, and `deleted` events.

### Migrations
- **Migration File**: `database/migrations/2026_06_13_000002_create_vendor_bills_tables.php`
  Creates the following tables:
  1. `vendor_bills` (stores bill reference, date, status, total amount, tax amount, discount, grand total, amount paid/due)
  2. `vendor_bill_items` (stores quantity, unit cost, tax, and line total for each product item on a bill)
  3. `vendor_bill_payments` (stores payment amount, date, reference, bank account/cash register ID, notes)

### Filament Resources
- **`VendorBillResource`**: `app/Filament/Resources/VendorBillResource.php`
  Defines the form and table interfaces for Vendor Bills.
- **Pages**:
  - `app/Filament/Resources/VendorBillResource/Pages/CreateVendorBill.php`
  - `app/Filament/Resources/VendorBillResource/Pages/EditVendorBill.php`
  - `app/Filament/Resources/VendorBillResource/Pages/ListVendorBills.php`
  - `app/Filament/Resources/VendorBillResource/Pages/ViewVendorBill.php`
- **Vendor Bill Payments**: No standalone Filament resource exists for payments. Payments are recorded inline via a custom Table Action `recordPayment` (labeled as **"Pay"**) in `VendorBillResource.php` (lines 209-263).

---

## 2. Double-Entry Accounting Verification

Double-entry journal entries are created via `app/Services/Accounting/JournalEntryService.php`.

### A. Bill Creation/Posting
*Trigger*: Saving a `VendorBill` with a status of `'posted'`, `'paid'`, or `'partially_paid'` (via `VendorBill::saved` callback calling `syncJournalEntry()`).
*Logic Code* (`app/Models/Accounting/VendorBill.php` lines 199-289):
```php
$apAccount = $service->getOrCreateAccount($business, 'liability', 'Accounts Payable', '2010', 'Accounts Payable');
$inventoryAccount = $service->getOrCreateAccount($business, 'asset', 'Inventory', '1210', 'Merchandise Inventory');
$discountAccount = $service->getOrCreateAccount($business, 'expense', 'Cost of Goods Sold', '5020', 'Purchase Discounts');
$taxInputAccount = $service->getOrCreateAccount($business, 'asset', 'Prepaid and Deferred Charges', '1320', 'Purchase Tax Paid');
```
The following postings are recorded:
- **Debit** `Inventory (1210)`: Amount equals the gross cost of the line items (`$grossCost`).
- **Debit** `Purchase Tax Paid (1320)`: Amount equals `$this->tax_amount`.
- **Credit** `Accounts Payable (2010)`: Amount equals `$this->grand_total`.
- **Credit** `Purchase Discounts (5020)`: Amount equals `$this->discount_amount`.

*Balance Check & Adjustment*:
- If rounding or discount calculations result in a difference between debits and credits, the difference (`$diff`) is adjusted on the `Inventory (1210)` debit line to keep the journal entry perfectly balanced.
- Draft or voided bills have their corresponding journal entries deleted.

**Conclusion**: The posting logic conforms to the specification: **Debit Inventory (1210) / Purchase Tax Paid (1320), Credit Accounts Payable (2010)**.

---

### B. Payment Recording
*Trigger*: Creating a `VendorBillPayment` (via `VendorBillPayment::created` callback calling `syncJournalEntry()`).
*Logic Code* (`app/Models/Accounting/VendorBillPayment.php` lines 143-181):
```php
$apAccount = $service->getOrCreateAccount($business, 'liability', 'Accounts Payable', '2010', 'Accounts Payable');
$account = $this->resolveAccount(); // Returns BankAccount or CashRegister
```
The resolved payment account must have a valid `account_id` mapping to a general ledger account. The following postings are recorded:
- **Debit** `Accounts Payable (2010)`: Amount equals `$this->amount`.
- **Credit** Cash/Bank (`$account->account_id`): Amount equals `$this->amount`.

**Conclusion**: The posting logic conforms to the specification: **Debit Accounts Payable (2010), Credit Cash/Bank**.

---

### C. Gaps and Vulnerabilities Identified

1. **Missing `updated` Event Handler in `VendorBillPayment`**:
   The `VendorBillPayment` model booted method includes event listeners for `created` and `deleted`, but **not** `updated`:
   ```php
   static::created(function (VendorBillPayment $payment): void {
       $payment->bill?->refreshTotals();
       $payment->syncJournalEntry();
   });

   static::deleted(function (VendorBillPayment $payment): void {
       $payment->bill?->refreshTotals();
       // deletes journal entry
   });
   ```
   If a user updates an existing payment amount, date, bank account, or description in Filament, the following issues will occur:
   - The associated bill's totals (`amount_paid` and `amount_due`) will not be recalculated automatically.
   - The journal entry will remain out of sync (reflecting the old amount or old account) since `syncJournalEntry()` is not called.
   *Recommendation*: Add a listener for `updated` or change the `created` callback to a `saved` callback in `VendorBillPayment.php`.

2. **Form Imports in `VendorBillResource.php`**:
   `VendorBillResource.php` uses `Get $get` and `Set $set` in closures, but does not explicitly import `Filament\Forms\Get` or `Filament\Forms\Set` in its `use` statements. Because tests pass, PHP might be resolving these dynamically or they are not hit during testing of other components, but adding proper `use` imports is standard practice.

---

## 3. Existing Tests

There is no dedicated `VendorBillResourceTest.php` or `VendorBillPaymentTest.php` under `tests/Feature/Filament/Resources/`. However, integration tests covering Vendor Bills exist in:

- **`tests/Feature/PeriodLockTest.php`**
  - `test_changing_vendor_bill_item_parent_checks_both_locks()`: Verifies that moving items between locked and unlocked bills fails appropriately.
  - `test_editing_vendor_bill_item_updates_totals_and_syncs_journal()`: Verifies that updating a line item's quantity successfully updates the vendor bill totals and syncs the double-entry journal entry.
  - `test_inventory_stock_reconciliation_on_updating_vendor_bill_item()`: Verifies that creating, updating, or deleting line items correctly reconciles and updates the product's `stock_quantity`.

All tests are functional and pass.

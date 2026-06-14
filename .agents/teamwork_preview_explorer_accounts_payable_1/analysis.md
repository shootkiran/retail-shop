# Accounts Payable Investigation Analysis

## Executive Summary
This analysis details the implementation of Accounts Payable (Vendor Bills and Payments) in the codebase. All relevant models, database migrations, and Filament resources have been located. The double-entry accounting journal entries are posted correctly for both vendor bill posting and payment recording. However, a crucial runtime defect was identified in the `VendorBillResource` where Filament's `Get` and `Set` classes are not imported, which would cause a crash upon editing line items.

---

## 1. File Locations

### Models
- **Vendor Bill**: `app/Models/Accounting/VendorBill.php`
- **Vendor Bill Item**: `app/Models/Accounting/VendorBillItem.php`
- **Vendor Bill Payment**: `app/Models/Accounting/VendorBillPayment.php`

### Observers
- There are no separate observer classes (e.g., in `app/Observers`). Instead, model events are handled natively within the models' Eloquent `booted()` methods:
  - **`VendorBill`**: Handles transaction period locking, ULID reference generation on creation, and journal entry synchronization/deletion on save, delete, and draft/void transitions.
  - **`VendorBillItem`**: Handles period locking, unit cost totals, stock adjustments (incrementing stock on create/update delta, decrementing on delete), and triggering parent bill total refreshing and journal synchronization.
  - **`VendorBillPayment`**: Handles period locking, ULID reference generation, parent bill total refreshing, and journal entry synchronization.

### Migrations
- `database/migrations/2026_06_13_000002_create_vendor_bills_tables.php` defines three tables:
  1. `vendor_bills`
  2. `vendor_bill_items`
  3. `vendor_bill_payments`

### Filament Resources
- **Vendor Bill Resource**: `app/Filament/Resources/VendorBillResource.php`
- **Resource Pages**:
  - `app/Filament/Resources/VendorBillResource/Pages/CreateVendorBill.php`
  - `app/Filament/Resources/VendorBillResource/Pages/EditVendorBill.php`
  - `app/Filament/Resources/VendorBillResource/Pages/ListVendorBills.php`
  - `app/Filament/Resources/VendorBillResource/Pages/ViewVendorBill.php`
- *Note*: Vendor Bill Payments do not have a separate resource class. Instead, payments are recorded using a custom modal action (`recordPayment`) directly on the `VendorBillResource` table view.

---

## 2. Double-Entry Accounting Verification

### A. Bill Creation / Posting
- **Trigger**: When a `VendorBill` status changes to `posted`, `paid`, or `partially_paid`, `syncJournalEntry()` is called.
- **Journal Entries Generated**:
  - **Debit**: Inventory (Account Code: `1210`) with the gross cost of the items.
  - **Debit**: Purchase Tax Paid (Account Code: `1320`) with the tax amount.
  - **Credit**: Accounts Payable (Account Code: `2010`) with the grand total.
  - **Credit**: Purchase Discounts (Account Code: `5020`) with the discount amount.
- **Balance / Correction Check**:
  - The system checks that the debits and credits balance. Any difference (`$diff`) is added/subtracted to the Inventory debit line to ensure complete balance.
  - This perfectly matches the double-entry accounting rule: Debit Inventory (1210) / Purchase Tax Paid (1320), Credit Accounts Payable (2010).

### B. Payment Recording
- **Trigger**: When a payment is created (`VendorBillPayment::created()`), `syncJournalEntry()` is called.
- **Journal Entries Generated**:
  - **Debit**: Accounts Payable (Account Code: `2010`) with the paid amount.
  - **Credit**: Cash / Bank (dynamic account ID resolved via `resolveAccount()` depending on whether the payment came from a `BankAccount` or `CashRegister`) with the paid amount.
- **Balance Check**:
  - The entry contains exactly two balanced lines (debit of X, credit of X).
  - This matches the double-entry accounting rule: Debit Accounts Payable (2010), Credit Cash/Bank.

---

## 3. Existing Tests
There are no standalone test classes dedicated specifically to `VendorBill` or `VendorBillPayment`.
However, they are covered in:
- `tests/Feature/PeriodLockTest.php`:
  - `test_changing_vendor_bill_item_parent_checks_both_locks()`: Verifies that moving items between locked and unlocked bills is prevented.
  - `test_editing_vendor_bill_item_updates_totals_and_syncs_journal()`: Verifies that changing item quantities updates the bill's grand total and syncs the corresponding journal entry correctly.
  - `test_inventory_stock_reconciliation_on_updating_vendor_bill_item()`: Verifies that adding, updating, or deleting a bill item updates the product's `stock_quantity` correctly in the database.

---

## 4. Identified Defect / Anomaly
In `app/Filament/Resources/VendorBillResource.php`, Filament's `Get` and `Set` utility classes are used as type hints in closure arguments but are **not** imported at the top of the file:
```php
// app/Filament/Resources/VendorBillResource.php:96
->afterStateUpdated(fn (Set $set, Get $get) => $set('total_amount', self::calculateLineTotal($get)))

// app/Filament/Resources/VendorBillResource.php:152
protected static function calculateLineTotal(Get $get): float

// app/Filament/Resources/VendorBillResource.php:161
protected static function updateTotals(Get $get, Set $set): void
```
Since `use Filament\Schemas\Components\Utilities\Get;` and `use Filament\Schemas\Components\Utilities\Set;` are missing, PHP will attempt to resolve them within the `App\Filament\Resources` namespace, causing a crash at runtime when the form is rendered or interacted with.

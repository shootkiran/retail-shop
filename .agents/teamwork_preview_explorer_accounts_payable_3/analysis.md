# Accounts Payable Investigation Report (Vendor Bills and Payments)

## 📌 Summary of Core Findings
- **Implementation Status**: The system implements an Accounts Payable module consisting of `VendorBill`, `VendorBillItem`, and `VendorBillPayment` models that utilize Laravel model events to handle real-time inventory adjustments and double-entry journal postings.
- **Journal Postings**: 
  - **Bill Posting**: Properly debits Inventory (`1210`) and Purchase Tax Paid (`1320`), while crediting Accounts Payable (`2010`) and Purchase Discounts (`5020`).
  - **Payment Posting**: Properly debits Accounts Payable (`2010`) and credits Cash/Bank.
- **Tests**: There are no dedicated test classes for vendor bills or payments, but their behavior is thoroughly validated by integration test cases in `tests/Feature/PeriodLockTest.php`.

---

## 📂 1. Directory of AP-related Files

The following files implement and configure the Accounts Payable system:

| Type | Path | Description |
|---|---|---|
| **Model** | `app/Models/Accounting/VendorBill.php` | Manages the main vendor bill record, totals recalculation, and journal syncing. |
| **Model** | `app/Models/Accounting/VendorBillItem.php` | Manages vendor bill line items, inventory stock increments, and triggers parent total updates. |
| **Model** | `app/Models/Accounting/VendorBillPayment.php` | Manages bill payments (cash/bank accounts) and creates payment journal entries. |
| **Filament Resource** | `app/Filament/Resources/VendorBillResource.php` | Provides the Back Office UI to manage Vendor Bills, line items, and record payments. |
| **Migration** | `database/migrations/2026_06_13_000001_create_journal_tables.php` | Defines the database schema for double-entry `journal_entries` and `journal_lines`. |
| **Migration** | `database/migrations/2026_06_13_000002_create_vendor_bills_tables.php` | Defines the database schema for `vendor_bills`, `vendor_bill_items`, and `vendor_bill_payments`. |
| **Tests** | `tests/Feature/PeriodLockTest.php` | Contains integration tests validating Vendor Bill totals, stock reconciliation, journal sync, and period locking. |

---

## ⚡ 2. Observers & Model Event Hooks

The system does not use separate observer classes under `app/Observers/`. Instead, it embeds the lifecycle events directly in the `booted()` method of each model.

### **VendorBill (`app/Models/Accounting/VendorBill.php:64-99`)**
- `saving`: Enforces transaction period lock checks based on `bill_date`.
- `creating`: Auto-generates a unique reference (e.g., `BILL-01H...`).
- `saved`: Recalculates totals and triggers `syncJournalEntry()` if the status is `posted`, `paid`, or `partially_paid`. If the status is draft or void, it deletes any pre-existing journal entry.
- `deleting`/`deleted`: Ensures period lock checks are run and deletes the associated journal entry.

### **VendorBillItem (`app/Models/Accounting/VendorBillItem.php:39-176`)**
- `saving`: Verifies period locks, calculates `total_amount` for the line item (i.e. `(quantity * unit_cost) + tax_amount`).
- `created`: Increments the associated product's inventory (`stock_quantity`) by the line item quantity.
- `updating`: Adjusts the product inventory stock by computing the delta between new and old quantity, or shifts stock between different product items if the product relation changed.
- `saved`: Updates parent bill totals and triggers journal entry sync.
- `deleted`: Decrements the product inventory stock and updates parent bill totals.

### **VendorBillPayment (`app/Models/Accounting/VendorBillPayment.php:48-77`)**
- `saving`/`deleting`: Enforces period lock checks based on `payment_date`.
- `creating`: Auto-generates a unique reference (e.g., `VBP-01H...`).
- `created`: Recalculates parent bill totals (updates `amount_paid`, `amount_due`, and updates status to `paid` or `partially_paid`), and triggers `syncJournalEntry()`.
- `deleted`: Updates parent bill totals and deletes the corresponding journal entry.

---

## 🧮 3. Double-Entry Accounting Journal Postings

The journal postings are dynamically executed via a helper service `App\Services\Accounting\JournalEntryService`.

### **A. Bill Creation/Posting**
Method: `VendorBill::syncJournalEntry()` (`app/Models/Accounting/VendorBill.php:199-289`)

Upon transitioning to `posted`, `paid`, or `partially_paid`, the bill creates a journal entry:
1. **Debit Inventory (1210)**:
   - Account Subtype: `Inventory`
   - Account Name: `Merchandise Inventory`
   - Value: `$grossCost` (sum of `quantity * unit_cost` across line items) + rounding discrepancy adjustment.
2. **Debit Purchase Tax Paid (1320)**:
   - Account Subtype: `Prepaid and Deferred Charges`
   - Account Name: `Purchase Tax Paid`
   - Value: `$this->tax_amount` (tax paid on purchases).
3. **Credit Accounts Payable (2010)**:
   - Account Subtype: `Accounts Payable`
   - Account Name: `Accounts Payable`
   - Value: `$this->grand_total` (the net liability amount).
4. **Credit Purchase Discounts (5020)**:
   - Account Subtype: `Cost of Goods Sold`
   - Account Name: `Purchase Discounts` (credited to offset expense)
   - Value: `$this->discount_amount`.

**Balancing Logic**:
`grand_total` is defined as: `total_amount - discount_amount + tax_amount`.
Thus, `grand_total + discount_amount = total_amount + tax_amount`, which means `Total Credits = Total Debits`.
Any minor rounding differences from floating point arithmetic are automatically added/subtracted from the Inventory debit entry to ensure the journal entry is perfectly balanced before saving.

---

### **B. Payment Recording**
Method: `VendorBillPayment::syncJournalEntry()` (`app/Models/Accounting/VendorBillPayment.php:143-181`)

When a payment is created:
1. Resolves the payment account (`BankAccount` or `CashRegister`).
2. **Debit Accounts Payable (2010)**:
   - Account Subtype: `Accounts Payable`
   - Value: `$this->amount`.
3. **Credit Cash/Bank**:
   - Account ID: The ledger account associated with the bank account or cash register (`$account->account_id`).
   - Value: `$this->amount`.

This maps perfectly to:
- Debit Accounts Payable (2010), Credit Cash/Bank.

---

## 🔍 4. UI Integration (Filament Resources)

The UI operations for vendor bills are handled in `app/Filament/Resources/VendorBillResource.php`.
- **Form Layout**: Comprises "Bill Details" (vendor, reference, dates, draft/posted/void status), a "Line Items" repeater, and a read-only "Financial Summary" section.
- **Line Items Repeater**: Features reactive calculations of total amounts when quantity, unit cost, or tax amount is modified.
- **Payment Interaction**: There is no standalone `VendorBillPaymentResource`. Instead, payments are recorded directly from the `VendorBillResource` list/view page via a table action called `recordPayment` (labeled as **"Pay"**). This action displays a form asking for payment date, payment account type (Bank Account or Cash Register), target account, amount (capped at `amount_due`), and reference. On submission, it creates a `VendorBillPayment` record.

### **Legacy vs. New Payment Actions**
There is a legacy payment action on the `VendorResource` called `makePayment` (`app/Filament/Resources/Vendors/Tables/VendorsTable.php:59`). This legacy action operates on a cash-basis system and writes a record directly to the `FinancialEntry` table (using `entry_type => 'vendor_payment'`, `direction => 'debit'`). It does not interface with `VendorBillPayment` or post double-entry general ledger journal entries, whereas the `recordPayment` action on `VendorBillResource` connects to the double-entry AP ledger.

---

## 🧪 5. Testing Coverage

The codebase contains no dedicated test files for vendor bills/payments (e.g. no `VendorBillResourceTest.php`). However, the functionality is verified via integration tests in `tests/Feature/PeriodLockTest.php`:

1. **`test_changing_vendor_bill_item_parent_checks_both_locks()`**
   - Asserts that shifting a line item between two vendor bills (one in a locked period, one in an unlocked period) is properly blocked by the transaction period lock.
2. **`test_editing_vendor_bill_item_updates_totals_and_syncs_journal()`**
   - Creates a vendor bill in `posted` status.
   - Verifies the parent bill `grand_total` updates.
   - Verifies that `JournalEntry` is automatically created, has the correct source relation, and matches the bill total.
   - Updates the line item quantity and verifies that both the bill `grand_total` and the journal entry's sum of debits update accordingly.
3. **`test_inventory_stock_reconciliation_on_updating_vendor_bill_item()`**
   - Verifies that when a vendor bill item is created, the associated product item stock is incremented.
   - Verifies that updating the item quantity adjusts the stock by the correct delta (positive and negative).
   - Verifies that changing the product on the line item decrements stock from the old product and increments stock on the new product.

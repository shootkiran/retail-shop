# Credit Notes & Customer Statements Investigation Report

## Executive Summary
This report details the implementation of **Credit Notes** and **Customer Statements** in the system. The codebase implements a robust, double-entry accounting model, automatic stock reconciliation on Credit Note item edits (created, updated, deleted), and dynamic PDF statement generation. 

---

## 1. Directory of Related Files (Models, Migrations, Routes, Controllers, Filament Resources)

### Models
- **CreditNote**: `app/Models/Accounting/CreditNote.php`
- **CreditNoteItem**: `app/Models/Accounting/CreditNoteItem.php`
- **Customer**: `app/Models/Customer.php`
- **CustomerPayment**: `app/Models/CustomerPayment.php`
- **ProductItem**: `app/Models/ProductItem.php`

### Migrations
- **Credit Notes Migration**: `database/migrations/2026_06_13_000003_create_credit_notes_tables.php`
- **Journal Entries Migration**: `database/migrations/2026_06_13_000001_create_journal_tables.php`

### Routes
- **Customer Statement Route**: `routes/web.php` (Line 14-15)
  ```php
  Route::get('customers/{customer}/statement', [CustomerStatementController::class, 'show'])
      ->name('customers.statement');
  ```

### Controllers
- **Customer Statement Controller**: `app/Http/Controllers/CustomerStatementController.php`

### Views
- **Customer Statement Blade Template**: `resources/views/reports/customer-statement.blade.php`

### Filament Resources
- **CreditNoteResource**: `app/Filament/Resources/CreditNoteResource.php`
- **Pages**:
  - `CreateCreditNote.php`
  - `EditCreditNote.php`
  - `ListCreditNotes.php`
  - `ViewCreditNote.php`
- **CustomerResource (where statement action is triggered)**: `app/Filament/Resources/Customers/CustomerResource.php`
  - Table Configuration: `app/Filament/Resources/Customers/Tables/CustomersTable.php` (Line 63-85 defines the 'statement' action).

---

## 2. Double-Entry Accounting Journal Entries for Credit Notes

When a Credit Note is created, updated, or deleted, a set of corresponding general ledger journal entries is managed. 

### A. Reversing Sales Revenue (in `CreditNote::syncJournalEntry`)
When a Credit Note is created or updated:
- **Debit: Sales Returns/Discounts (Account Code 4020)**
  - Amount: Gross Revenue (sum of `quantity * unit_price` across items).
- **Debit: Sales Tax Payable (Account Code 2120)**
  - Amount: Credit Note `tax_amount`.
- **Credit: Accounts Receivable (Account Code 1110)**
  - Amount: Credit Note `grand_total` (Gross Revenue - Discount + Tax).

If there are minor rounding differences between total credits and debits, the difference is adjusted automatically on the Debit line of the Sales Returns/Discounts account.

### B. Restocking Inventory (in `CreditNote::syncJournalEntry`)
For the product inventory return (COGS adjustment), a separate journal entry under reference `{reference}-COGS` is posted:
- **Debit: Merchandise Inventory (Account Code 1210)**
  - Amount: Total Cost (calculated as `quantity * (product->unit_cost ?? unit_price * 0.6)`).
- **Credit: Cost of Sales / COGS (Account Code 5010)**
  - Amount: Total Cost.

### C. Reversing on Deletion
When a Credit Note is deleted, the `deleted` hook in `CreditNote::booted()` performs the following:
1. Re-increments the customer's outstanding balance:
   ```php
   $note->customer?->increment('outstanding_balance', (float) $note->grand_total);
   ```
2. Deletes the primary journal entry:
   ```php
   JournalEntry::withoutGlobalScopes()
       ->where('source_type', $note->getMorphClass())
       ->where('source_id', $note->getKey())
       ->delete();
   ```
3. Deletes the COGS adjustment journal entry:
   ```php
   JournalEntry::withoutGlobalScopes()
       ->where('reference', $note->reference.'-COGS')
       ->delete();
   ```

---

## 3. Stock Quantity Reconciliation (in `CreditNoteItem`)

Stock levels of `ProductItem` are dynamically updated during the lifecycle of a `CreditNoteItem` using Eloquent model hooks:

- **On Creation (`created` hook)**:
  Increments stock quantity (restocking the items):
  ```php
  ProductItem::query()
      ->whereKey($item->product_item_id)
      ->increment('stock_quantity', (float) $item->quantity);
  ```

- **On Deletion (`deleted` hook)**:
  Decrements stock quantity (reversing the restock when the item is removed from credit note):
  ```php
  ProductItem::query()
      ->whereKey($item->product_item_id)
      ->decrement('stock_quantity', (float) $item->quantity);
  ```

- **On Updating (`updating` hook)**:
  Handles product changes and quantity changes:
  1. **Product item changed**: Decrements stock of the original product by its original quantity, and increments stock of the new product by its new quantity.
  2. **Product item same, quantity changed**: Calculates the difference (`$delta = new_qty - old_qty`) and increments the stock of the product by `$delta`.

---

## 4. Customer Statement PDF Generation

Customer statements are dynamically generated as PDF files on-the-fly and streamed to the user:

- **Trigger Point**:
  The `CustomersTable` configuration contains a custom `statement` action:
  - Prompts the user with a modal containing `startDate` and `endDate` inputs.
  - Redirects to route `customers.statement` passing the customer record, start date, and end date.

- **Calculation Logic (in `CustomerStatementController@show`)**:
  1. **Opening Balance**: Calculated as `salesBefore - paymentsBefore - creditsBefore` for transactions before `startDate`.
  2. **Transactions**: Fetches Sales, Payments, and Credit Notes in the date range. Maps them to ledger entries (Debits/Credits).
  3. **Running Balance**: Sorts all transactions chronologically and loops to compute the running balance starting from the opening balance.
  4. **PDF Render**: Loads the view `reports.customer-statement` with calculated balances and uses `Barryvdh\DomPDF\Facade\Pdf::loadView` to render and stream the PDF.

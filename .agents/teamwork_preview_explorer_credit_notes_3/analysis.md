# Credit Notes and Customer Statements Investigation Report

## Core Summary
This report analyzes the existing implementation of Credit Notes and Customer Statements in the retail-shop POS application. It maps the files involved, evaluates the double-entry accounting journal entry generation, assesses inventory restocking logic, details the customer statement PDF generation flow, and highlights critical architectural issues/gaps in customer balance and inventory tracking.

---

## 📂 File Locations

### 1. Credit Notes
- **Models**:
  - `App\Models\Accounting\CreditNote` (booted hooks, totals calculation, journal entries sync)
  - `App\Models\Accounting\CreditNoteItem` (booted hooks for stock updates, total calculation, parent totals refresh)
- **Observers**:
  - None. Model events are defined directly in the `booted()` method of each model.
- **Migrations**:
  - `database/migrations/2026_06_13_000003_create_credit_notes_tables.php` (defines `credit_notes` and `credit_note_items` tables)
- **Filament Resources**:
  - `App\Filament\Resources\CreditNoteResource`
  - `App\Filament\Resources\CreditNoteResource\Pages\CreateCreditNote`
  - `App\Filament\Resources\CreditNoteResource\Pages\EditCreditNote`
  - `App\Filament\Resources\CreditNoteResource\Pages\ListCreditNotes`
  - `App\Filament\Resources\CreditNoteResource\Pages\ViewCreditNote`

### 2. Customer Statements
- **Routes**:
  - `Route::get('customers/{customer}/statement', [CustomerStatementController::class, 'show'])->name('customers.statement');` in `routes/web.php`
- **Controllers**:
  - `App\Http\Controllers\CustomerStatementController`
- **Views**:
  - `resources/views/reports/customer-statement.blade.php` (Blade template designed for PDF generation via DomPDF)
- **Filament Integration**:
  - `App\Filament\Resources\Customers\Tables\CustomersTable` (defines the `statement` Action modal on the customer list/tables, redirecting to the controller route with `startDate` and `endDate`)

---

## ⚖️ Double-Entry Accounting Journal Entries

The journal entries are posted using `App\Services\Accounting\JournalEntryService` during the `CreditNote::syncJournalEntry()` method.

### 1. Sales Revenue Reversal
- **Debit: Sales Returns / Discounts (4020)** (labeled `Sales Discounts` in the DB seed, representing account code `4020`).
  - *Amount*: Gross total of returned items (`quantity * unit_price` or `total_amount` if items are empty).
  - *Adjustment*: If there is a `discount_amount` on the credit note, the difference is subtracted directly from the Debit amount of this account to keep the entry balanced (representing net revenue returned).
- **Debit: Sales Tax Payable (2120)** (labeled `Sales Tax Payable`).
  - *Amount*: Credit Note's `tax_amount`.
- **Credit: Accounts Receivable (1110)** (labeled `Accounts Receivable`).
  - *Amount*: Credit Note's `grand_total`.

### 2. Inventory Restocking (COGS Reversal)
Posted as a secondary journal entry with reference suffix `-COGS`:
- **Debit: Merchandise Inventory (1210)**.
  - *Amount*: Total Cost of returned items (`quantity * product.unit_cost`).
- **Credit: Cost of Goods Sold (5010)** (labeled `Cost of Sales` in DB, representing account code `5010`).
  - *Amount*: Total Cost of returned items.
- *Total Cost Calculation*: Uses the product item's `unit_cost` attribute (`$item->product?->unit_cost`). If the relationship/cost is not set, it defaults to `unit_price * 0.6`.

---

## 📦 Inventory Restocking & Stock Adjustments

Stock quantities are adjusted automatically using Eloquent lifecycle hooks in `CreditNoteItem`:

1. **Item Creation (`created` event)**:
   - Increments the related `ProductItem` stock quantity by the returned quantity:
     ```php
     ProductItem::query()
         ->whereKey($item->product_item_id)
         ->increment('stock_quantity', (float) $item->quantity);
     ```
2. **Item Deletion (`deleted` event)**:
   - Reverts the restocked inventory by decrementing the related `ProductItem` stock quantity:
     ```php
     ProductItem::query()
         ->whereKey($item->product_item_id)
         ->decrement('stock_quantity', (float) $item->quantity);
     ```
3. **Item Update (`updating` event)**:
   - Handles product changes by decrementing original product stock and incrementing new product stock.
   - Handles quantity updates on the same product by computing the delta (`new_quantity - original_quantity`) and incrementing/decrementing accordingly.

---

## 📄 Customer Statement PDF Generation

Customer Statement generation is implemented via `laravel-dompdf` using the following flow:

1. **Trigger**:
   - The user selects the `Statement` action on a customer record within the `CustomersTable`. A modal prompts for `startDate` and `endDate`.
   - On submission, the user is redirected to `customers/{customer}/statement?startDate=...&endDate=...`.
2. **Controller Logic (`CustomerStatementController::show`)**:
   - **Opening Balance**: Calculates the customer's opening balance prior to `startDate` using:
     $$\text{Opening Balance} = \text{Sales Total} - \text{Payments Total} - \text{Credit Notes Total}$$
   - **Activity Range**: Fetches Sales, Customer Payments, and Credit Notes within the date range, mapping them to a unified format (`date`, `type`, `reference`, `description`, `debit`, `credit`).
   - **Sorting & Running Balance**: Combines and sorts all transactions chronologically, then loops to calculate the running balance at each step:
     - Debits (Sales) increase the balance.
     - Credits (Payments, Credit Notes) decrease the balance.
   - **PDF Rendering**: Loads the view `reports.customer-statement` with the ledger data and passes it to `Barryvdh\DomPDF\Facade\Pdf::loadView(...)`.
   - **Streaming**: Streams the generated PDF inline.
3. **View Layout (`reports/customer-statement.blade.php`)**:
   - Built with clean, absolute-width HTML tables and standard CSS styling.
   - Uses `DejaVu Sans, sans-serif` as the font family to ensure correct character rendering in DomPDF.
   - Displays a summary block (Opening Balance, Total Activity, Amount Due) followed by a chronological transaction activity ledger table.

---

## ⚠️ Identified Gaps and Crucial Issues

During our read-only analysis of the models and lifecycle events, two significant data consistency bugs were identified:

### 1. Database Cascade Delete bypasses Eloquent Stock Adjustment
- **Issue**: `CreditNoteItem` uses Eloquent's `deleted` event to decrement the product's `stock_quantity`. However, the migration specifies `cascadeOnDelete()` on the `credit_note_id` foreign key:
  ```php
  $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
  ```
- **Consequence**: When a parent `CreditNote` is deleted, the database deletes all associated `CreditNoteItem` records at the database level. Laravel's Eloquent events (`deleting` / `deleted`) do **not** fire for `CreditNoteItem`.
- **Result**: The stock that was incremented during Credit Note creation is **never** reverted when a Credit Note is deleted. The inventory remains permanently inflated.

### 2. Parent update saveQuietly() bypasses Customer Outstanding Balance update
- **Issue**: `CreditNoteItem::saved` and `deleted` call `CreditNote::refreshTotals()`, which updates the parent's totals:
  ```php
  if ($this->isDirty(['total_amount', 'tax_amount', 'grand_total'])) {
      $this->saveQuietly();
  }
  ```
  `saveQuietly()` prevents Eloquent events from firing on the `CreditNote`.
- **Consequence**:
  - `CreditNote::created` decrements customer `outstanding_balance` by `grand_total`.
  - `CreditNote::deleted` increments customer `outstanding_balance` by `grand_total`.
  - There is no `CreditNote::updated` event hook, and even if there was, `saveQuietly()` bypasses it.
- **Result**: If a user updates/adds/removes items from an existing Credit Note, the credit note's `grand_total` changes, but the customer's `outstanding_balance` is **never updated** to reflect the new total. The customer's outstanding balance becomes desynchronized with the actual Credit Note grand totals.

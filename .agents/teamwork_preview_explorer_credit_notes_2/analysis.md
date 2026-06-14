# Credit Notes and Customer Statements Investigation Report

## 1. Directory of Files Located

The following models, observers, migrations, routes, controllers, views, and Filament resources implement Credit Notes and Customer Statements in the codebase:

### Models & Logic
* **Credit Note Model:** `app/Models/Accounting/CreditNote.php`
  * Contains the core double-entry accounting journal synchronization (`syncJournalEntry()`) and lifecycle methods (reversing customer outstanding balance upon creation/deletion, checking fiscal period lock).
* **Credit Note Item Model:** `app/Models/Accounting/CreditNoteItem.php`
  * Handles lines inside a credit note, including recalculating totals and updating the product's stock quantity when items are created, updated, or deleted.
* **Accounting/JournalEntry Service:** `app/Services/Accounting/JournalEntryService.php`
  * Offers helper methods `createEntry` (which enforces balanced double-entry transactions) and `getOrCreateAccount` to verify or provision ledger accounts dynamically.

### Database Migrations
* **Credit Notes Tables Migration:** `database/migrations/2026_06_13_000003_create_credit_notes_tables.php`
  * Creates `credit_notes` and `credit_note_items` tables with fields for references, customer, sale, amounts, quantities, and dates.

### Observers
* **Lifecycle Hooks:** Eloquent observers are defined directly within the models' `booted()` method:
  * `CreditNote::booted()` (managing business settings period locks, setting references, decrementing/incrementing customer outstanding balances, and syncing journal entries).
  * `CreditNoteItem::booted()` (verifying locks, updating line totals, adjusting product stock quantities, and calling parent totals/journal synchronization).

### Controllers & Routes
* **Routes:** `routes/web.php` (line 14)
  * Defines `customers.statement` route pointing to `CustomerStatementController@show`.
* **Controller:** `app/Http/Controllers/CustomerStatementController.php`
  * Calculates opening balance, gathers chronological transactions (Invoices, Payments, Credit Notes) within the date range, computes running ledger balances, and loads the PDF stream.

### Blade Views
* **PDF Template:** `resources/views/reports/customer-statement.blade.php`
  * Formatted using custom CSS and `DejaVu Sans` font for standard A4 layout. Renders statement summaries and the activity ledger table.

### Filament Resources
* **Credit Note Resource:** `app/Filament/Resources/CreditNoteResource.php`
  * Admin interface config containing form fields, totals updates, and lists under the "Accounting" navigation group.
* **Customer Table Action:** `app/Filament/Resources/Customers/Tables/CustomersTable.php` (lines 63–85)
  * Implements the "Statement" modal action that prompts for date range parameters and redirects to the PDF statement generator.

---

## 2. Double-Entry Accounting Verification for Credit Notes

### Reversing Sales Revenue
In `CreditNote::syncJournalEntry()`:
* **Accounts used:**
  * Accounts Receivable (`1110`)
  * Sales Discounts / Returns (`4020`)
  * Sales Tax Payable (`2120`)
* **Journal Entries Posted:**
  * **Debit:** `4020` (Sales Discounts/Returns) with the gross revenue of returned items (`grossRevenue = quantity * unit_price`).
  * **Debit:** `2120` (Sales Tax Payable) with the credit note's `tax_amount`.
  * **Credit:** `1110` (Accounts Receivable) with the credit note's `grand_total`.
* **Rounding Adjustments:** If the debits and credits do not match exactly due to fractional/tax rounding, the difference is adjusted on the Sales Returns account (`4020`) line.

### Restocking Inventory
Also in `CreditNote::syncJournalEntry()`:
* **Accounts used:**
  * Merchandise Inventory (`1210`)
  * Cost of Sales / COGS (`5010`)
* **Journal Entries Posted:**
  * **Debit:** `1210` (Merchandise Inventory) with the calculated `totalCost`.
  * **Credit:** `5010` (Cost of Sales / COGS) with the calculated `totalCost`.
* **COGS Calculation:** `totalCost = items->sum(quantity * (product->unit_cost ?? unit_price * 0.6))`. If a product doesn't have a cost value, a fallback of 60% of the unit price is used.
* **Entry reference:** Suffix `-COGS` (e.g. `CN-XXX-COGS`).

### Stock Quantities Synchronization
This is fully handled within `CreditNoteItem::booted()`:
* **On Item Creation:** Increments `ProductItem::stock_quantity` by `quantity`.
* **On Item Update:**
  * If the product ID is updated, the original product's stock is decremented by its original quantity, and the new product's stock is incremented by its new quantity.
  * If only the quantity changes, the difference (delta) is calculated and applied to the product's stock.
* **On Item Deletion:** Decrements `ProductItem::stock_quantity` by `quantity`.

---

## 3. Customer Statement PDF Generation

### Workflow & implementation Details
1. **Triggering Point:** Inside the customers list table, clicking the "Statement" action opens a Filament modal form requiring `startDate` and `endDate`. Submitting redirects to:
   `route('customers.statement', ['customer' => $record, 'startDate' => ..., 'endDate' => ...])`
2. **Controller Logic (`CustomerStatementController@show`):**
   * **Opening Balance:** Calculated by summing all sales (Invoices) before the start date, and subtracting payments and credit notes:
     `openingBalance = SalesBefore - PaymentsBefore - CreditsBefore`
   * **Transactions Range:** Sales, Payments, and Credit Notes within the range are mapped into a unified layout (`date`, `type`, `reference`, `description`, `debit`, `credit`) and sorted chronologically by date.
   * **Running Balances:** A loop computes the running balance for each transaction relative to the opening balance:
     * Invoices add to the balance (Debits).
     * Payments and Credit Notes subtract from the balance (Credits).
   * **PDF Render:** Loads the view `reports.customer-statement` with variables using `Barryvdh\DomPDF\Facade\Pdf::loadView(...)` and streams it.
3. **Template styling:**
   * Utilizes `DejaVu Sans` to handle currency symbol representation and general text formatting.
   * Renders the opening balance, period activity total, closing balance, and the statement ledger rows.

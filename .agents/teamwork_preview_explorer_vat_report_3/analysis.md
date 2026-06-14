# Tax / VAT Report Investigation Report

This report presents findings from investigating the implementation of Milestone 4: Advanced Taxation & VAT Report Page (R3) in the codebase.

## 1. Location of Tax Report Files
The following files comprise the Tax / VAT Report functionality in the system:
- **Filament Page (Backend)**: `app/Filament/Pages/Accounting/TaxReport.php`
- **Blade View (Frontend)**: `resources/views/filament/pages/accounting/tax-report.blade.php`

---

## 2. Analysis of Calculation Logic

The `TaxReport` page calculates and aggregates VAT amounts using double-entry ledger accounts.

### Current Implementation Limits
- **Input Tax (VAT paid on purchases)**: Aggregated successfully.
- **Output Tax (VAT collected on sales)**: Aggregated successfully.
- **Net Payable / Credit**: Calculated as `Tax Output - Tax Input`.
- **Taxable Sales**: **Not implemented** in the current `TaxReport.php` page or its Blade view. There is no logic or query to aggregate or display gross taxable sales amounts.
- **Taxable Purchases**: **Not implemented** in the current `TaxReport.php` page or its Blade view. There is no logic or query to aggregate or display gross taxable purchase amounts.

### Details of VAT Aggregation
In the `getTaxDataProperty()` method of `TaxReport.php`:
1. **Tax Output Calculation**:
   - Queries the `accounts` table for code `2120` (Sales Tax Payable) belonging to the current business.
   - Sums all `credit` and `debit` values in `journal_lines` associated with this account where the parent `journal_entries.entry_date` falls between the selected `startDate` and `endDate`.
   - **Formula**: `Tax Output = Credit (collected) - Debit (refunded / reversed via Credit Notes)`.

2. **Tax Input Calculation**:
   - Queries the `accounts` table for code `1320` (Purchase Tax Paid) belonging to the current business.
   - Sums all `debit` and `credit` values in `journal_lines` associated with this account where the parent `journal_entries.entry_date` falls between the selected `startDate` and `endDate`.
   - **Formula**: `Tax Input = Debit (paid on purchases/bills) - Credit (returned / refunded)`.

3. **Net Tax Payable**:
   - **Formula**: `Net Payable = Tax Output - Tax Input`.
   - Rounded to 2 decimal places.

---

## 3. Database Tables, Models, and Fields Queried

The following database tables, models, and columns are queried to perform the tax aggregations:

### 1. `accounts` Table (`App\Models\Accounting\Account` model)
- **Fields queried**:
  - `id`: Used to identify the account record.
  - `business_id`: Used to filter accounts belonging to the current business.
  - `code`: Queried for matching string values `'2120'` (Sales Tax Payable) or `'1320'` (Purchase Tax Paid).

### 2. `journal_entries` Table (`App\Models\Accounting\JournalEntry` model)
- **Fields queried**:
  - `id`: Joined with `journal_lines.journal_entry_id`.
  - `business_id`: Used to filter journal entries for the current business.
  - `entry_date`: Used to filter journal entries within the selected `startDate` and `endDate` range.

### 3. `journal_lines` Table (`App\Models\Accounting\JournalLine` model)
- **Fields queried**:
  - `journal_entry_id`: Used for joining with `journal_entries`.
  - `account_id`: Used to filter lines belonging to the matching Tax Output (`2120`) or Tax Input (`1320`) account.
  - `debit`: Summed to calculate debit aggregates.
  - `credit`: Summed to calculate credit aggregates.

---

## 4. Existing Tests for the Tax Report
- A thorough search of the codebase was conducted (including checking the `tests/Feature/Filament/Pages/` directory and searching for occurrences of the word `TaxReport`).
- **Result**: There are **no existing tests** specifically targeting the Tax / VAT Report page, its controller methods, or the date-filtering/aggregation logic.
- Standard reports page tests exist (e.g., `tests/Feature/Filament/Pages/ReportsPageTest.php` and `tests/Feature/Filament/Pages/InventoryReportPageTest.php`), but they only verify high-level charts or inventory calculations.
- General resource tests (like `SaleResourceTest.php` and `PurchaseResourceTest.php`) only verify static `tax_amount` storage in the `sales` and `purchases` tables.

---

## 5. Recommendations for Implementing Missing Requirements (Taxable Sales & Purchases)
To display **taxable sales** and **taxable purchases** as required by Milestone 4:
1. **Aggregate Taxable Sales**:
   - Query the Sales Revenue account (code `4010`) and Sales Discounts/Returns (code `4020`).
   - Calculate gross revenue: `Credit - Debit` for code `4010`, less any `Debit - Credit` for code `4020`.
   - Alternatively, back-calculate from output tax if a flat VAT rate is assumed, but querying the revenue accounts is far more robust and correct.
2. **Aggregate Taxable Purchases**:
   - Query the Merchandise Inventory account (code `1210`) for debits originating from Purchases or Vendor Bills.
   - Sum the base taxable cost of items.
3. **UI Updates**:
   - Add display cards for "Taxable Sales Amount" and "Taxable Purchases Amount" in `resources/views/filament/pages/accounting/tax-report.blade.php`.

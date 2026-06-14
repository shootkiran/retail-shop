# Tax / VAT Report Investigation Report

## Executive Summary
The accounting system's Tax / VAT Report page has been successfully located and analyzed. While it correctly calculates and displays **Tax Output** (VAT collected), **Tax Input** (VAT paid), and **Net Tax Payable/Refundable** by querying the general ledger, it currently lacks any logic or UI elements to aggregate and display **Taxable Sales** and **Taxable Purchases** as required by the R3 specification. No existing tests for this page were found in the test suite.

---

## 1. File Locations
- **Filament Page Class**: `app/Filament/Pages/Accounting/TaxReport.php`
- **Blade View Template**: `resources/views/filament/pages/accounting/tax-report.blade.php`

---

## 2. Analysis of the Calculation Logic

### Current Calculation Logic
The current implementation in `TaxReport.php` calculates the tax data via a Livewire computed property (`getTaxDataProperty`):

1. **Tax Output (VAT Collected)**:
   - Queries the `Account` model to find the account with code `'2120'` ("Sales Tax Payable").
   - If found, it queries `JournalLine` and joins `JournalEntry` to sum debits and credits within the filtered date range (`startDate` to `endDate`).
   - Calculated as: `Tax Output = Credit (collected) - Debit (refunded)`.
   - Represents VAT collected from customers on sales minus sales tax refunded (via credit notes).

2. **Tax Input (VAT Paid)**:
   - Queries the `Account` model to find the account with code `'1320'` ("Purchase Tax Paid").
   - If found, it queries `JournalLine` and joins `JournalEntry` to sum debits and credits within the filtered date range.
   - Calculated as: `Tax Input = Debit (paid) - Credit (returned)`.
   - Represents VAT paid on supplier purchases/bills.

3. **Net Tax Payable / (Credit)**:
   - Calculated as: `Net Payable = Tax Output - Tax Input`.
   - A positive balance indicates liability due to the tax authority; a negative balance indicates a carry-forward tax credit.

### The Implementation Gap (Missing Taxable Bases)
The R3 specification states:
> *"Display taxable sales & tax output collected, taxable purchases & tax input paid, and net tax payable/refundable."*

Currently, **Taxable Sales** and **Taxable Purchases** are **not calculated in the PHP page class** and **not displayed in the Blade template**. The view only shows the tax amounts (Output, Input) and the Net Payable.

---

## 3. Database Schema, Models, and Fields Queried

The page executes queries using the following database tables, models, and fields:

| Model | Table | Fields Queried | Description / Purpose |
|---|---|---|---|
| `App\Models\Accounting\Account` | `accounts` | `business_id`, `code`, `id` | Locates the specific ledger accounts for Sales Tax Payable (`2120`) and Purchase Tax Paid (`1320`). |
| `App\Models\Accounting\JournalLine` | `journal_lines` | `journal_entry_id`, `account_id`, `debit`, `credit` | Fetches and aggregates the debits/credits posted to the tax accounts. |
| `App\Models\Accounting\JournalEntry` | `journal_entries` | `id`, `business_id`, `entry_date` | Filters the ledger lines by business scope and date range. |

---

## 4. Test Verification
A search across the test suite (`tests/` directory) returned **no existing test cases** or references to `TaxReport` or `tax-report`. No unit or feature tests currently verify the VAT report calculations or page access.

---

## 5. Proposed Changes to Align with R3 Specification

To complete Milestone 4 and fully satisfy R3, the following updates are proposed:

### A. Calculation Logic Update (in `TaxReport.php`)
We can compute the taxable bases using either a ledger-based approach or a model-based approach. The **ledger-based approach** is more aligned with double-entry principles, as it accounts for manual journal adjustments.

#### 1. Taxable Sales (Ledger-based)
- Sales Revenue (Account Code `4010` - Credit normal) and Sales Returns/Discounts (Account Code `4020` - Debit normal).
- `Taxable Sales = (4010 Credit - 4010 Debit) - (4020 Debit - 4020 Credit)`.

#### 2. Taxable Purchases (Ledger-based)
- Merchandise Inventory (Account Code `1210` - Debit normal) and Purchase Discounts (Account Code `5020` - Credit normal).
- Because `1210` is also affected by Sales COGS, we must filter by `journal_entries.source_type` matching `App\Models\Purchase` or `App\Models\Accounting\VendorBill`.
- `Taxable Purchases = (1210 Debit - 1210 Credit) - (5020 Credit - 5020 Debit)` filtered by `source_type` in `[Purchase, VendorBill]`.

#### Alternative: Model-based Approach
- **Taxable Sales**:
  ```php
  $salesBase = Sale::where('business_id', $business->id)
      ->whereBetween('sold_at', [$this->startDate, $this->endDate])
      ->sum(DB::raw('total_amount - discount_amount'));

  $returnsBase = CreditNote::where('business_id', $business->id)
      ->whereBetween('refunded_at', [$this->startDate, $this->endDate])
      ->sum('total_amount');

  $taxableSales = $salesBase - $returnsBase;
  ```
- **Taxable Purchases**:
  ```php
  $billBase = VendorBill::where('business_id', $business->id)
      ->where('status', 'posted')
      ->whereBetween('bill_date', [$this->startDate, $this->endDate])
      ->sum(DB::raw('total_amount - discount_amount'));

  $purchaseBase = Purchase::where('business_id', $business->id)
      ->whereBetween('purchased_at', [$this->startDate, $this->endDate])
      ->sum(DB::raw('total_amount - discount_amount'));

  $taxablePurchases = $billBase + $purchaseBase;
  ```

### B. UI Update (in `tax-report.blade.php`)
Update the summary table/breakdown layout to list:
1. **Taxable Sales** (e.g. `10,000.00`) alongside **Tax Output** (e.g. `1,300.00`).
2. **Taxable Purchases** (e.g. `5,000.00`) alongside **Tax Input** (e.g. `650.00`).
3. **Net Tax Payable / Refundable** (e.g. `650.00`).

---

## 6. Proposed Test Case Sketch
Create `tests/Feature/Filament/Pages/TaxReportTest.php` to verify:
1. Backoffice/Manager user can access the page.
2. The page correctly aggregates sales tax from `Sale` models and purchase tax from `VendorBill`/`Purchase` models in the specified date range.
3. The page displays the computed taxable sales, taxable purchases, input tax, output tax, and net payable.

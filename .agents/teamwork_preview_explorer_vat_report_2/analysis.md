# Tax / VAT Report Implementation Analysis

This report details the investigation into the implementation of **Milestone 4: Advanced Taxation & VAT Report Page (R3)** in the codebase.

---

## 1. Location of the Tax Report Filament Page

The Tax Report page is implemented in the following files:
- **Filament Page Class**: `app/Filament/Pages/Accounting/TaxReport.php`
- **Blade View Template**: `resources/views/filament/pages/accounting/tax-report.blade.php`

---

## 2. Calculation Logic & Aggregation Analysis

The page calculates input tax, output tax, and net tax payable dynamically over a date range. It uses the general ledger double-entry system instead of querying tables like `sales` or `purchases` directly. 

### A. Tax Output (VAT collected on sales)
* **GL Account**: `Sales Tax Payable` (Account Code `2120`, Liability).
* **Source Transactions**: Credit entries from `Sale` checkouts (sales tax collected) and Debit entries from `CreditNote` refunds/returns (sales tax reversed).
* **Formula**:
  $$\text{Tax Output} = \text{Credit Postings} - \text{Debit Postings}$$
* **Code Implementation**:
  ```php
  $taxOutput = $outputCredit - $outputDebit;
  ```

### B. Tax Input (VAT paid on purchases)
* **GL Account**: `Purchase Tax Paid` (Account Code `1320`, Asset/Prepaid).
* **Source Transactions**: Debit entries from `Purchase` or `VendorBill` registrations (tax paid on purchases) and Credit entries from purchase returns/reversals (if any).
* **Formula**:
  $$\text{Tax Input} = \text{Debit Postings} - \text{Credit Postings}$$
* **Code Implementation**:
  ```php
  $taxInput = $inputDebit - $inputCredit;
  ```

### C. Net Tax Payable / (Credit)
* **Formula**:
  $$\text{Net Tax Payable} = \text{Tax Output} - \text{Tax Input}$$
* **Code Implementation**:
  ```php
  'net_payable' => round($taxOutput - $taxInput, 2)
  ```
  * If the result is $\ge 0$, it is the net amount due to the tax authority.
  * If the result is $< 0$, it represents a carry-forward tax credit.

### D. Taxable Sales & Taxable Purchases
The page **does not** explicitly calculate or aggregate "taxable sales" or "taxable purchases" (the gross bases prior to tax application). It focuses exclusively on the tax/VAT amounts posted to the respective tax ledgers.

---

## 3. Database Schema, Models & Fields Queried

The page queries the following database entities:

| Model | Table | Fields Queried / Filtered | Purpose |
|---|---|---|---|
| `App\Models\Accounting\Account` | `accounts` | `id`, `business_id`, `code` | Fetches the accounts with code `2120` (Output Tax) and `1320` (Input Tax) for the current business. |
| `App\Models\Accounting\JournalLine` | `journal_lines` | `id`, `journal_entry_id`, `account_id`, `debit`, `credit` | Fetches and sums all debits/credits posted to the respective tax accounts. |
| `App\Models\Accounting\JournalEntry` (implicit) | `journal_entries` | `id`, `business_id`, `entry_date` | Joined to filter journal lines by the current business and date range filter (`startDate` / `endDate`). |

### SQL Query Outline
For a given account, the query executed is:
```sql
SELECT SUM(journal_lines.debit) as total_debit, SUM(journal_lines.credit) as total_credit
FROM journal_lines
INNER JOIN journal_entries ON journal_lines.journal_entry_id = journal_entries.id
WHERE journal_entries.business_id = ?
  AND journal_lines.account_id = ?
  AND journal_entries.entry_date BETWEEN ? AND ?
```

---

## 4. Analysis of Testing Status

* **No Existing Tests**: There are currently no unit or feature tests written specifically for the Tax / VAT Report page (no files in the `tests` directory reference the `TaxReport` class or contain `tax-report` / `VAT` keywords).
* **Compilation Blocker**: The project test suite currently cannot be bootstrapped due to a duplicate import bug in `app/Filament/Resources/VendorBillResource.php`. 
  * Specifically, lines 20-21 and 33-34 duplicate the imports of `Filament\Forms\Get` and `Filament\Forms\Set`:
    ```php
    20: use Filament\Schemas\Components\Utilities\Get;
    21: use Filament\Schemas\Components\Utilities\Set;
    ...
    33: use Filament\Schemas\Components\Utilities\Get;
    34: use Filament\Schemas\Components\Utilities\Set;
    ```
  * This raises a `FatalError` (`Cannot use Filament\Forms\Get as Get because the name is already in use`), preventing any Artisan commands or test suites from executing.

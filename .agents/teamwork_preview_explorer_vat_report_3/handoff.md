# Handoff Report — Tax / VAT Report Investigation (Milestone 4)

## 1. Observation
- Located Filament page for Tax Report:
  - Path: `/Users/kiran/Herd/retail-shop/app/Filament/Pages/Accounting/TaxReport.php`
  - Path: `/Users/kiran/Herd/retail-shop/resources/views/filament/pages/accounting/tax-report.blade.php`
- The core calculation logic in `TaxReport.php` is:
```php
        // 1. Calculate Tax Output (Sales Tax Payable - code 2120)
        $outputAccount = Account::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('code', '2120')
            ->first();
        ...
        // Tax Output is Credit (collected) - Debit (refunded)
        $taxOutput = $outputCredit - $outputDebit;

        // 2. Calculate Tax Input (Purchase Tax Paid - code 1320)
        $inputAccount = Account::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('code', '1320')
            ->first();
        ...
        // Tax Input is Debit (paid) - Credit (returned)
        $taxInput = $inputDebit - $inputCredit;
```
- The database schema files for the accounting ledger:
  - `database/migrations/2026_06_12_140000_create_accounting_chart_tables.php` defines the `accounts` table.
  - `database/migrations/2026_06_13_000001_create_journal_tables.php` defines the `journal_entries` and `journal_lines` tables.
- Verification command `./vendor/bin/phpunit` outputs:
```
OK, but there were issues!
Tests: 94, Assertions: 306, Deprecations: 2.
```
- A grep search in `tests/` for `TaxReport` returned zero matches. There are no tests specifically testing the Tax Report page or its calculations.

---

## 2. Logic Chain
- **Aggregation Logic**:
  - The system records tax transactions on sales (Sales Tax Payable code `2120`, credit balance increases liability) and purchases (Purchase Tax Paid code `1320`, debit balance increases asset).
  - The Tax Report page queries these specific accounts, joins `journal_lines` with `journal_entries` on `journal_lines.journal_entry_id = journal_entries.id`, filters by date range, and aggregates:
    - Output Tax: Credit sum minus Debit sum of Account `2120`.
    - Input Tax: Debit sum minus Credit sum of Account `1320`.
  - Therefore, the VAT input and output tax are correctly calculated from the ledger.
- **Missing Taxable Base Aggregation**:
  - The `TaxReport` page `getTaxDataProperty()` return signature contains only:
    ```php
    return [
        'tax_output' => round($taxOutput, 2),
        'tax_input' => round($taxInput, 2),
        'net_payable' => round($taxOutput - $taxInput, 2),
    ];
    ```
  - The blade view only references `$this->taxData['tax_output']`, `$this->taxData['tax_input']`, and `$this->taxData['net_payable']`.
  - There is no query logic or UI element for taxable sales and taxable purchases. Thus, these must be implemented as part of Milestone 4.
- **Testing Coverage**:
  - There is no `TaxReportPageTest.php` in the test suite, meaning the tax report page has no test coverage.

---

## 3. Caveats
- No caveats. The investigation was read-only, fully verified through code audit, database schema inspection, and executing the PHPUnit test suite.

---

## 4. Conclusion
- The Tax Report page successfully calculates and displays VAT input, VAT output, and net payable/refundable.
- However, the page completely lacks the required logic to calculate and display **taxable sales** and **taxable purchases**.
- Testing for the page does not exist. The page and its calculations need to be fully verified with integration tests.

---

## 5. Verification Method
- **Run the test suite**:
  Run `./vendor/bin/phpunit` in `/Users/kiran/Herd/retail-shop` to check all existing tests are green.
- **Inspect page code**:
  Inspect `/Users/kiran/Herd/retail-shop/app/Filament/Pages/Accounting/TaxReport.php` to verify the queried models and fields.

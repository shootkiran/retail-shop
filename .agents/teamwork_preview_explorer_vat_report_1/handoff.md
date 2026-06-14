# Handoff Report — Explorer 1

## 1. Observation
- **Filament Page Class**: Located at `app/Filament/Pages/Accounting/TaxReport.php`.
  Lines 54-104:
  ```php
  // 1. Calculate Tax Output (Sales Tax Payable - code 2120)
  $outputAccount = Account::withoutGlobalScopes()
      ->where('business_id', $business->id)
      ->where('code', '2120')
      ->first();

  $outputDebit = 0.0;
  $outputCredit = 0.0;

  if ($outputAccount) {
      $outputQuery = JournalLine::query()
          ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
          ->where('journal_entries.business_id', $business->id)
          ->where('journal_lines.account_id', $outputAccount->id)
          ->whereBetween('journal_entries.entry_date', [$this->startDate, $this->endDate]);

      $outputDebit = (float) $outputQuery->sum('debit');
      $outputCredit = (float) $outputQuery->sum('credit');
  }

  // Tax Output is Credit (collected) - Debit (refunded)
  $taxOutput = $outputCredit - $outputDebit;

  // 2. Calculate Tax Input (Purchase Tax Paid - code 1320)
  $inputAccount = Account::withoutGlobalScopes()
      ->where('business_id', $business->id)
      ->where('code', '1320')
      ->first();

  $inputDebit = 0.0;
  $inputCredit = 0.0;

  if ($inputAccount) {
      $inputQuery = JournalLine::query()
          ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
          ->where('journal_entries.business_id', $business->id)
          ->where('journal_lines.account_id', $inputAccount->id)
          ->whereBetween('journal_entries.entry_date', [$this->startDate, $this->endDate]);

      $inputDebit = (float) $inputQuery->sum('debit');
      $inputCredit = (float) $inputQuery->sum('credit');
  }

  // Tax Input is Debit (paid) - Credit (returned)
  $taxInput = $inputDebit - $inputCredit;

  return [
      'tax_output' => round($taxOutput, 2),
      'tax_input' => round($taxInput, 2),
      'net_payable' => round($taxOutput - $taxInput, 2),
  ];
  ```
- **Blade Template**: Located at `resources/views/filament/pages/accounting/tax-report.blade.php`. It references and displays only `$this->taxData['tax_output']`, `$this->taxData['tax_input']`, and `$this->taxData['net_payable']`. No mentions of taxable sales or taxable purchases exist in the code.
- **Ledger Entries mapping**:
  - `Sale` model maps gross sales items (taxable sales) to credit `4010` (Sales Revenue) and discounts to debit `4020` (Sales Returns/Discounts).
  - `CreditNote` model maps gross revenue returned to debit `4020` (Sales Returns/Discounts).
  - `VendorBill` and `Purchase` models map items to debit `1210` (Inventory) and discounts to credit `5020` (Purchase Discounts).
- **Existing Tests**: A full grep search of the `tests/` directory for the word `TaxReport` or `tax-report` yielded `No results found`, confirming there are no existing tests for this page.

---

## 2. Logic Chain
- **Step 1**: The R3 specification requires displaying:
  1. Taxable sales & tax output collected.
  2. Taxable purchases & tax input paid.
  3. Net tax payable/refundable.
- **Step 2**: Based on the direct observations of the class `TaxReport.php` and the template `tax-report.blade.php`, the page aggregates and displays:
  - Tax Output Collected (`tax_output` from account `2120`)
  - Tax Input Paid (`tax_input` from account `1320`)
  - Net Tax Payable/Refundable (`net_payable` as `tax_output - tax_input`)
- **Step 3**: The page does **not** calculate, aggregate, or display "taxable sales" or "taxable purchases".
- **Conclusion**: There is a clear gap in the implementation of the R3 requirement where the taxable base amounts (taxable sales and taxable purchases) are missing from the Tax Report page.

---

## 3. Caveats
- We assume that "taxable sales" corresponds to net sales revenue subject to tax (e.g. Sales Revenue `4010` minus Sales Returns/Discounts `4020`), which is recorded in the ledger.
- We assume that "taxable purchases" corresponds to the cost of inventory purchases (which is debited to Merchandise Inventory `1210` from `Purchase` and `VendorBill` models, net of purchase discounts `5020`).
- No physical modifications were made to the codebase as this is a read-only investigation.

---

## 4. Conclusion
The Filament Tax / VAT Report is implemented in `app/Filament/Pages/Accounting/TaxReport.php`, but lacks the aggregation and display of **taxable sales** and **taxable purchases**.
To complete the implementation, the developer must:
1. Update `TaxReport::getTaxDataProperty` to query the ledger (or operational models) for the taxable sales and taxable purchases within the date range.
2. Update `tax-report.blade.php` to display these values alongside their respective tax amounts.
3. Create `tests/Feature/Filament/Pages/TaxReportTest.php` to verify the page loads, aggregates correctly, and displays the required fields.

---

## 5. Verification Method
- **File Inspection**: Check `app/Filament/Pages/Accounting/TaxReport.php` to confirm if `taxable_sales` and `taxable_purchases` have been added.
- **Blade Template Inspection**: Check `resources/views/filament/pages/accounting/tax-report.blade.php` to confirm if the taxable fields are rendered.
- **Test Command**: Execute the project tests using:
  ```bash
  vendor/bin/phpunit
  ```
  Once the new tests are added, they should specifically cover the `TaxReport` page.

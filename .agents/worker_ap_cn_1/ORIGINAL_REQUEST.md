## 2026-06-13T15:40:22Z
Objective: Verify and test Accounts Payable (R1) and Credit Notes & Customer Statements (R2).

Instructions:
1. Fix `app/Filament/Resources/VendorBillResource.php`:
   - Add missing imports:
     `use Filament\Schemas\Components\Utilities\Get;`
     `use Filament\Schemas\Components\Utilities\Set;`
2. Create `tests/Feature/VendorBillTest.php` to test:
   - Creating a Vendor Bill with status "draft" does not generate a journal entry.
   - Posting a Vendor Bill (status: posted) creates a balanced journal entry (debiting Inventory `1210` / Purchase Tax Paid `1320` and crediting Accounts Payable `2010`).
   - Recording a bill payment (partial and full) creates a balanced journal entry (debiting Accounts Payable `2010`, crediting cash/bank) and correctly updates the bill status and outstanding balance.
3. Create `tests/Feature/CreditNoteTest.php` to test:
   - Creating a Credit Note posts a balanced journal entry (debiting Sales Returns/Discounts `4020` and Sales Tax Payable `2120`, crediting Accounts Receivable `1110`).
   - Reversing COGS/Inventory (debiting Merchandise Inventory `1210`, crediting COGS `5010`).
   - Verifying that stock quantity of the product is correctly restocked (incremented).
   - Fetching the customer statement PDF via the route `customers.statement` (`/customers/{customer}/statement`) with a date range, checking it returns a PDF and computes correct balances.
4. Run phpunit and confirm all tests pass successfully.

MANDATORY INTEGRITY WARNING:
> DO NOT CHEAT. All implementations must be genuine. DO NOT
> hardcode test results, create dummy/facade implementations, or
> circumvent the intended task. A Forensic Auditor will independently
> verify your work. Integrity violations WILL be detected and your
> work WILL be rejected.

Inputs:
- Working directory: /Users/kiran/Herd/retail-shop/.agents/worker_ap_cn_1

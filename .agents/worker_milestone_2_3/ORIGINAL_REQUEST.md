## 2026-06-13T09:56:39Z
You are a worker agent.
Your working directory is: /Users/kiran/Herd/retail-shop/.agents/worker_milestone_2_3
Your task is to implement the missing fixes and tests for Milestone 2 (Accounts Payable) and Milestone 3 (Credit Notes & Customer Statements).

Specifically:
1. Fix VendorBillResource.php to import `Filament\Forms\Get` and `Filament\Forms\Set` since they are used in form fields but not imported.
2. In VendorBillPayment.php model, add/verify model lifecycle hooks for update/saved. Currently, only created and deleted are handled, meaning that updates to payments do not refresh the bill totals and do not sync the journal entries. Ensure updates are fully handled and synchronized.
3. Review CreditNote.php and CreditNoteItem.php to ensure all required fields are correctly synchronized and tested.
4. Write comprehensive integration/feature tests:
   - tests/Feature/Filament/Resources/VendorBillResourceTest.php: Test the draft-to-posted transitions of bills, recording payments, and verifying journal entry creations and AP balance updates.
   - tests/Feature/Filament/Resources/CreditNoteResourceTest.php: Test the credit note creation, restocking inventory, customer statement PDF route rendering and verifying journal entries.
5. Run the tests and ensure they all pass.

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Please report your completion and provide the test command and results.

## 2026-06-13T07:47:55Z
Objective: Secure the Period Locking Control (R4) implementation against bypasses/loopholes.

Instructions:
1. Update `app/Models/Accounting/JournalEntry.php`:
   - Normalize dates using `startOfDay()` (e.g. `$entryDate = \Carbon\Carbon::parse($this->entry_date)->startOfDay();`) before comparing them to the lock date.
   - On `saving` and `deleting`, check if the entry's date is locked.
   - Specifically:
     - If creating: check if the new `entry_date` is on or before the lock date.
     - If updating: check if either the original `entry_date` (before update) OR the new `entry_date` is on or before the lock date. Also check if the `business_id` is changed; if so, verify against both the original and new business lock dates.
     - If deleting: check if the `entry_date` is on or before the lock date.
2. Update `app/Models/Accounting/JournalLine.php`:
   - Add `saving` and `deleting` event hooks in a `booted()` method.
   - Both hooks should fetch the parent `JournalEntry` (e.g. `$line->entry`) and call `$entry->checkPeriodLock()`.
3. Protect Transaction Models from modifications/deletions inside locked periods. Add `saving` and `deleting` hooks to the following models checking their transaction date against the business's `period_lock_date`:
   - `App\Models\Sale` (date field: `sold_at`)
   - `App\Models\Purchase` (date field: `purchased_at`)
   - `App\Models\Accounting\VendorBill` (date field: `bill_date`)
   - `App\Models\Accounting\VendorBillPayment` (date field: `payment_date`)
   - `App\Models\Accounting\CreditNote` (date field: `refunded_at`)
   - `App\Models\CustomerPayment` (date field: `payment_date`)
   - Tip: You can query the lock date using:
     ```php
     $lockDate = \App\Models\BusinessSetting::withoutGlobalScopes()
         ->where('business_id', $model->business_id)
         ->value('period_lock_date');
     ```
     If the lock date is set and the transaction date (original or new) is on or before it (using `startOfDay()`), throw a `\RuntimeException` with an appropriate message.
4. Add comprehensive test cases to `tests/Feature/PeriodLockTest.php` to verify:
   - Modifying a `JournalLine` directly on a locked `JournalEntry` is blocked.
   - Deleting a `JournalLine` directly on a locked `JournalEntry` is blocked.
   - Deleting a `Sale` inside a locked period is blocked.
   - Modifying a `Sale` date to/from a locked period is blocked.
   - Date comparison checks are timezone/time portion safe (e.g. `2026-06-01 12:00:00` is blocked if lock date is `2026-06-01`).
5. Run the entire test suite using `vendor/bin/phpunit` to ensure all tests pass.

MANDATORY INTEGRITY WARNING:
> DO NOT CHEAT. All implementations must be genuine. DO NOT
> hardcode test results, create dummy/facade implementations, or
> circumvent the intended task. A Forensic Auditor will independently
> verify your work. Integrity violations WILL be detected and your
> work WILL be rejected.

Inputs:
- Working directory: /Users/kiran/Herd/retail-shop/.agents/worker_period_lock_2

Outputs:
- Write a detailed handoff report in /Users/kiran/Herd/retail-shop/.agents/worker_period_lock_2/handoff.md.
- Message the caller (orchestrator) with the absolute path when complete.

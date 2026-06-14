# Handoff Report: Period Locking Control (R4) Review

## 1. Observation
- **File Changes Reviewed**:
  - `app/Filament/Resources/BusinessSettings/BusinessSettingResource.php`: Added `DatePicker::make('period_lock_date')` to the localization section and a corresponding table column.
  - `app/Models/Accounting/JournalEntry.php`: Added `booted()` hooks for `saving` and `deleting` which call `$entry->checkPeriodLock()`.
  - `tests/Feature/PeriodLockTest.php`: Contains 9 test cases verifying that creating, updating, and deleting journal entries on or before the lock date fails, and after the lock date succeeds.
- **Migration Reviewed**:
  - `database/migrations/2026_06_13_000004_add_period_lock_date_to_business_settings_table.php`: Adds `period_lock_date` column to `business_settings`.
- **Test Executions**:
  - Ran `vendor/bin/phpunit tests/Feature/PeriodLockTest.php` -> 9 tests passed.
  - Ran `vendor/bin/phpunit` -> 75 tests passed (all tests in the suite).

---

## 2. Logic Chain
- **Date Comparison Hook**:
  - The model `JournalEntry` checks `period_lock_date` from the business settings. If the `entry_date` is less than or equal to the lock date, or if the original `entry_date` was less than or equal to the lock date, it throws a `RuntimeException`.
  - This successfully intercepts Filament/Eloquent mutations of `JournalEntry`.
- **Loopholes Identified**:
  - **Time Portion Bypass**: If a datetime Carbon instance (e.g., `'2026-06-01 12:00:00'`) is assigned to `entry_date`, it will bypass the `lessThanOrEqualTo` comparison with the lock date (`'2026-06-01 00:00:00'`), yet it will be saved as `2026-06-01` in the database.
  - **JournalLine Modifications**: The period lock check is only on the parent `JournalEntry`. Since `JournalLine` is a separate model without a `$touches` configuration or hooks, direct updates, deletions, or additions of lines under a locked entry bypass the check.
  - **Business ID Transfer**: If a locked `JournalEntry` has its `business_id` updated to another business (with no lock), the check only runs against the new business's settings, bypassing the original business's lock.

---

## 3. Caveats
- Direct database mutations (e.g. raw SQL or database seeders) bypass Eloquent hooks. This is a standard Laravel caveat, but application-level Eloquent hooks should cover all model-level entry points.
- No UI tests were run; we verified Filament code by manual code analysis.

---

## 4. Conclusion & Review Verdict
- **Verdict**: **REQUEST_CHANGES** (due to implementation gaps/loopholes in date comparison and journal lines lockout).

---

## 5. Verification Method
- Run `vendor/bin/phpunit tests/Feature/PeriodLockTest.php` to verify existing tests.
- Inspect `app/Models/Accounting/JournalEntry.php` and `app/Models/Accounting/JournalLine.php`.

---

# Quality Review Report

## Review Summary
- **Verdict**: **REQUEST_CHANGES**
- **Overall Assessment**: The implementation is syntactically correct and aligns with standard Filament/Laravel Resource and Eloquent pattern, but contains several logical and integrity vulnerabilities.

## Findings

### [Critical] Finding 1: Time-portion Bypass
- **What**: Comparing dates with time portions can bypass the lock.
- **Where**: `app/Models/Accounting/JournalEntry.php` (lines 68, 77)
- **Why**: Carbon comparisons like `Carbon::parse('2026-06-01 12:00:00')->lessThanOrEqualTo('2026-06-01 00:00:00')` evaluate to `false`. Because the database column type is `date` (not `datetime`), the database discards the time portion, saving the transaction as `2026-06-01` (locked date), bypassing validation.
- **Suggestion**: Use `startOfDay()` or string formatting:
  ```php
  $entryDate = Carbon::parse($this->entry_date)->startOfDay();
  $lockDate = Carbon::parse($settings->period_lock_date)->startOfDay();
  ```

### [Major] Finding 2: JournalLine Integrity Loophole
- **What**: Modifying or deleting `JournalLine` records belonging to a locked `JournalEntry` is allowed.
- **Where**: `app/Models/Accounting/JournalLine.php`
- **Why**: `JournalLine` has no Eloquent hook checking the parent `JournalEntry` lock status, nor does it touch the parent to trigger the parent's `saving` hook. Thus, lines can be altered or removed from locked entries.
- **Suggestion**: Add a `saving` and `deleting` hook in `JournalLine` that delegates validation to the parent `JournalEntry`:
  ```php
  static::booted(function () {
      static::saving(fn (JournalLine $line) => $line->entry?->checkPeriodLock());
      static::deleting(fn (JournalLine $line) => $line->entry?->checkPeriodLock());
  });
  ```

### [Major] Finding 3: Business ID Mutation Bypass
- **What**: Changing `business_id` to an unlocked business bypasses the original lock.
- **Where**: `app/Models/Accounting/JournalEntry.php` (lines 59-61)
- **Why**: The lock check only queries the `BusinessSetting` of the *new* `business_id`. If a transaction is moved from a locked business to an unlocked business, the check will not enforce the original business's lock.
- **Suggestion**: If `business_id` is dirty, retrieve the original `business_id`'s lock date and check against that as well.

### [Minor] Finding 4: Unmodified Save Exception
- **What**: Calling `save()` on an unmodified locked `JournalEntry` throws an exception.
- **Where**: `app/Models/Accounting/JournalEntry.php` (lines 44-46)
- **Why**: The `saving` hook fires on every save. If the record is unmodified (not dirty), throwing a `RuntimeException` blocks saving unmodified instances (e.g. in batch operations).
- **Suggestion**: Check if the model is dirty before validating, or skip checking if no relevant fields changed.

## Verified Claims
- Creating/updating/deleting journal entries on or before the lock date fails -> Verified via `vendor/bin/phpunit tests/Feature/PeriodLockTest.php` -> PASS.
- Entries after the lock date succeed -> Verified via `vendor/bin/phpunit tests/Feature/PeriodLockTest.php` -> PASS.

## Coverage Gaps
- **BusinessSetting UI tests**: No feature/integration tests exist to verify that the `period_lock_date` field renders in Filament and persists correctly to the database.
- **JournalLine stress-testing**: No tests exist to verify if modifications to `JournalLine` are blocked.

---

# Adversarial Challenge Report

## Challenge Summary
- **Overall risk assessment**: **HIGH**

## Challenges

### [Critical] Challenge 1: Datetime input bypasses lock check
- **Assumption challenged**: Dates are always compared as timezone-agnostic date-only objects.
- **Attack scenario**: A user/API provides `entry_date` as a datetime string (e.g. `2026-06-01 01:00:00`) matching the lock date. The comparison evaluates to `false`, and the entry saves successfully.
- **Blast radius**: Allows modifying or creating transactions inside locked periods.
- **Mitigation**: Normalize dates to `startOfDay()` before comparing.

### [High] Challenge 2: Line modifications on locked transactions
- **Assumption challenged**: Modifying a transaction's amounts or accounts is blocked if the transaction is locked.
- **Attack scenario**: A user directly saves or deletes `JournalLine` objects belonging to a locked `JournalEntry`. Since there are no checks on `JournalLine`, the changes are written to the database.
- **Blast radius**: The financial ledger's integrity is compromised because values/accounts can be changed within locked periods.
- **Mitigation**: Implement saving/deleting hooks on `JournalLine`.

### [Medium] Challenge 3: Business transfer bypasses lock
- **Assumption challenged**: Only date-based modifications are checked.
- **Attack scenario**: A user updates `business_id` of a locked transaction to an unlocked business.
- **Blast radius**: Transaction is moved to another business context, modifying history without respect to the lock.
- **Mitigation**: Validate the original `business_id`'s lock date.

## Stress Test Results
- Datetime bypass scenario -> Expected: Blocked -> Actual: Allowed -> **FAIL**
- Journal line modification scenario -> Expected: Blocked -> Actual: Allowed -> **FAIL**
- Business ID transfer scenario -> Expected: Blocked -> Actual: Allowed -> **FAIL**

## Unchallenged Areas
- Filament admin dashboard access control / user permissions -> Out of scope.

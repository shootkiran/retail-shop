# Execution Plan: Advanced Accounting & Business Operations Features

This document outlines the detailed execution plan for implementing the missing advanced accounting and business operations features for the Simple Retail POS and Accounting system.

---

## 📅 Milestones & Feature Breakdown

### Milestone 1: Period Locking Control (R4)
- **Objective**: Expose period locking UI and verify transactional safety.
- **Tasks**:
  1. Add `period_lock_date` DatePicker field and table column to `BusinessSettingResource.php`.
  2. Implement/verify unit and feature tests in `tests/Feature/PeriodLockTest.php`.
  3. Ensure trying to write or modify journal entries on/before the lock date throws a `RuntimeException`.

### Milestone 2: Accounts Payable Integration & Tests (R1)
- **Objective**: Implement comprehensive tests and verify accrual double-entry logic for Vendor Bills and Payments.
- **Tasks**:
  1. Audit existing `VendorBill` and `VendorBillPayment` models to verify exact journal entry postings:
     - Bill creation: Debit Inventory (`1210`) / Purchase Tax Paid (`1320`), Credit Accounts Payable (`2010`).
     - Payment recording: Debit Accounts Payable (`2010`), Credit Cash/Bank.
  2. Write feature tests in `tests/Feature/Filament/Resources/VendorBillResourceTest.php`:
     - Test draft to posted status transitions and journal entry creation.
     - Test full and partial payment recording via Filament action and verifying cash/bank accounts vs AP account balances.

### Milestone 3: Credit Notes & Customer Statement PDFs (R2)
- **Objective**: Verify credit note double-entry and customer statement PDF generation.
- **Tasks**:
  1. Audit existing `CreditNote` and `CreditNoteItem` model logic to ensure:
     - Reversing sales revenue (debit Sales Returns `4020` / Tax Payable `2120`, credit Accounts Receivable `1110`).
     - Restocking inventory (debit Inventory `1210`, credit COGS `5010`).
     - Re-incrementing and decrementing stock quantities on item creation/deletion.
  2. Write integration tests in `tests/Feature/Filament/Resources/CreditNoteResourceTest.php`:
     - Test Credit Note creation, outstanding balance reduction, and journal entry posts.
     - Test Customer Statement PDF route rendering and check binary PDF response output.

### Milestone 4: Advanced Taxation & VAT Report Page (R3)
- **Objective**: Verify TaxReport Filament page functionality.
- **Tasks**:
  1. Verify the calculations on `app/Filament/Pages/Accounting/TaxReport.php`.
  2. Write integration tests in `tests/Feature/Accounting/TaxReportTest.php` to simulate sales and purchases with tax amounts and verify aggregate values match.

### Milestone 5: Fixed Assets & Depreciation Register (R5)
- **Objective**: Create the entire fixed asset tracking and monthly straight-line depreciation engine.
- **Tasks**:
  1. **Database Migration**: Create `fixed_assets` table:
     - `id`, `business_id` (foreignId), `name` (string), `asset_code` (string), `purchase_date` (date), `purchase_cost` (decimal), `salvage_value` (decimal), `useful_life_years` (integer), `depreciated_amount` (decimal), `last_depreciation_date` (date), `status` (string), timestamps.
  2. **Model**: Create `App\Models\Accounting\FixedAsset.php` with appropriate relationships, fillables, and scopes.
  3. **Console Command**: Create `App\Console\Commands\RunDepreciation.php` (registered as `accounting:run-depreciation`):
     - Calculate monthly straight-line depreciation: `(purchase_cost - salvage_value) / (useful_life_years * 12)`.
     - Generate a balanced journal entry for each active asset:
       - Debit: Depreciation Expense (`5120`)
       - Credit: Accumulated Depreciation (`1220`)
     - Update asset's `depreciated_amount` and `last_depreciation_date` (and transition status to `fully_depreciated` when book value reaches salvage value).
  4. **Filament UI**: Create `app/Filament/Resources/FixedAssetResource.php` to list and manage assets, displaying original cost, accumulated depreciation, and current book value.
  5. **Tests**: Create `tests/Feature/FixedAssetTest.php` to verify:
     - Filament management of fixed assets.
     - Monthly depreciation calculation accuracy and journal entry postings.

---

## 🔬 Testing & Verification Plan

### Automated tests:
For each milestone, a corresponding PHPUnit test suite must be created and verified:
- `tests/Feature/PeriodLockTest.php`
- `tests/Feature/Filament/Resources/VendorBillResourceTest.php`
- `tests/Feature/Filament/Resources/CreditNoteResourceTest.php`
- `tests/Feature/Accounting/TaxReportTest.php`
- `tests/Feature/FixedAssetTest.php`

### Manual Verification:
- Verify that Filament pages render and process forms correctly.
- Verify that DB migrations run successfully.

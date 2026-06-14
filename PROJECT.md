# Project: Simple Retail POS and Accounting Upgrades

## Architecture
- **Laravel + Livewire + FilamentPHP**: Backoffice UI, resources, and custom pages.
- **SQLite Database**: Relational schema containing customers, vendors, products, sales, purchases, journal entries/lines, vendor bills, credit notes, and fixed assets.
- **Double-Entry Ledger System**: Centralized `JournalEntry` and `JournalLine` models that track financial transactions across Asset, Liability, Equity, Revenue, and Expense accounts.
  - Accounts Payable: Account `2010` (Liability)
  - Accounts Receivable: Account `1110` (Asset)
  - Merchandise Inventory: Account `1210` (Asset)
  - Sales Tax Payable: Account `2120` (Liability)
  - Purchase Tax Paid: Account `1320` (Asset)
  - Sales Returns/Discounts: Account `4020` (Revenue Offset / Expense)
  - Depreciation Expense: Account `5120` (Expense)
  - Accumulated Depreciation: Account `1220` (Asset Offset / Contra-Asset)

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|--------------|--------|
| 1 | Period Locking UI & Safety (R4) | Integrate UI setting, verify runtime exception locking on journal entries | none | DONE |
| 2 | Accounts Payable Verification (R1) | Verify Vendor Bill / Payment journal entry syncing, write feature tests | M1 | PLANNED |
| 3 | Credit Notes & Customer Statement Verification (R2) | Verify customer balance updates, journal entries, statement PDF downloads, write feature tests | M1 | PLANNED |
| 4 | VAT & Tax Report Verification (R3) | Verify TaxReport page aggregates and displays taxable sales/purchases and VAT output/input, write tests | M1 | PLANNED |
| 5 | Fixed Assets & Depreciation (R5) | Implement fixed asset model, migration, console command for depreciation, Filament resource, and tests | M1 | PLANNED |

## Interface Contracts
### Period Lock Event Hooks
- `JournalEntry::saving` & `JournalEntry::deleting`: Check if `entry_date` <= `period_lock_date`. Throws `RuntimeException` on lock.

### Monthly Depreciation
- Console command: `php artisan accounting:run-depreciation`
  - Calculates straight-line depreciation for each asset.
  - Posts to `Depreciation Expense (5120)` (Debit) and `Accumulated Depreciation (1220)` (Credit).

## Code Layout
- Models: `app/Models/Accounting/`
- Filament Resources: `app/Filament/Resources/`
- Custom Filament Pages: `app/Filament/Pages/`
- Controllers: `app/Http/Controllers/`
- Views: `resources/views/`
- Command: `app/Console/Commands/`
- Tests: `tests/Feature/`

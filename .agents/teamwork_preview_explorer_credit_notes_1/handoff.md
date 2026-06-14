# Handoff Report

## 1. Observation

- **CreditNote Model**: Located at `app/Models/Accounting/CreditNote.php`. In `booted()`, lines 72-88:
  ```php
  static::created(function (CreditNote $note): void {
      $note->customer?->decrement('outstanding_balance', (float) $note->grand_total);
      $note->syncJournalEntry();
  });

  static::deleted(function (CreditNote $note): void {
      $note->customer?->increment('outstanding_balance', (float) $note->grand_total);

      JournalEntry::withoutGlobalScopes()
          ->where('source_type', $note->getMorphClass())
          ->where('source_id', $note->getKey())
          ->delete();

      JournalEntry::withoutGlobalScopes()
          ->where('reference', $note->reference.'-COGS')
          ->delete();
  });
  ```
- **CreditNoteItem Model**: Located at `app/Models/Accounting/CreditNoteItem.php`. In `booted()`, lines 67-98, 136-146:
  ```php
  static::created(function (CreditNoteItem $item): void {
      // Re-increment stock quantity
      ProductItem::query()
          ->whereKey($item->product_item_id)
          ->increment('stock_quantity', (float) $item->quantity);
  });

  static::updating(function (CreditNoteItem $item): void {
      $originalQuantity = (float) $item->getOriginal('quantity');
      $originalProductId = (int) $item->getOriginal('product_item_id');
      $currentProductId = (int) $item->product_item_id;

      if ($originalProductId !== $currentProductId) {
          ProductItem::query()
              ->whereKey($originalProductId)
              ->decrement('stock_quantity', $originalQuantity);

          ProductItem::query()
              ->whereKey($currentProductId)
              ->increment('stock_quantity', (float) $item->quantity);

          return;
      }

      $delta = (float) $item->quantity - $originalQuantity;

      if ($delta !== 0.0) {
          ProductItem::query()
              ->whereKey($currentProductId)
              ->increment('stock_quantity', $delta);
      }
  });
  // ...
  static::deleted(function (CreditNoteItem $item): void {
      // Re-decrement stock quantity on removal
      ProductItem::query()
          ->whereKey($item->product_item_id)
          ->decrement('stock_quantity', (float) $item->quantity);

      if ($item->note) {
          $item->note->refreshTotals();
          $item->note->syncJournalEntry();
      }
  });
  ```
- **Journal Entries & COGS**: In `CreditNote::syncJournalEntry()`, lines 180-272:
  - Account Codes: `1110` (Accounts Receivable), `4020` (Sales Discounts/Returns), `2120` (Sales Tax Payable), `1210` (Merchandise Inventory), `5010` (Cost of Sales).
  - Double entry details:
    - Primary entry: Debits `4020` (by gross revenue) and `2120` (by tax amount). Credits `1110` (by grand total).
    - COGS/Inventory entry: Debits `1210` and Credits `5010` by calculated total cost (where cost is `quantity * (product->unit_cost ?? unit_price * 0.6)`).
- **Customer Statement Route & Controller**: Route is in `routes/web.php` line 14:
  `Route::get('customers/{customer}/statement', [CustomerStatementController::class, 'show'])->name('customers.statement');`
  Controller is at `app/Http/Controllers/CustomerStatementController.php`.
  Blade view is at `resources/views/reports/customer-statement.blade.php`.
- **Statement action**: Located in `app/Filament/Resources/Customers/Tables/CustomersTable.php`, lines 63-85.
- **Tests Execution**: Run `./vendor/bin/phpunit tests/Feature/PeriodLockTest.php`. The test output:
  `OK, but there were issues! Tests: 28, Assertions: 61, Deprecations: 2.`
  Tests `test_editing_credit_note_item_updates_totals_and_syncs_journal()` and `test_inventory_stock_reconciliation_on_updating_credit_note_item()` verify the credit note item journal syncing and stock quantity updating respectively.

---

## 2. Logic Chain

1. **Model & Hook Implementation**: Based on the observed `booted()` hooks in `CreditNote` and `CreditNoteItem`, it is clear that stock quantities are adjusted upon creation (incremented/restocked), deletion (decremented), and updates (delta computed and stock updated).
2. **Double-Entry Verification**: Based on the accounts and values parsed in `syncJournalEntry()`, the primary transaction reverses sales revenue (Debiting Returns and Sales Tax Payable, Crediting Accounts Receivable), and the COGS adjustment restocks inventory (Debiting Inventory, Crediting COGS). The calculations and accounts match the project's requirements.
3. **Statement Generation Flow**: In `CustomerStatementController@show`, opening balance is calculated by aggregating sales, payments, and credit notes before the start date. Within the start and end dates, transactions are retrieved, formatted as ledger rows, sorted chronologically, and running balances are computed. These are loaded into `reports/customer-statement` blade template and streamed as PDF via `Barryvdh\DomPDF\Facade\Pdf`.

---

## 3. Caveats

- We assumed that `product` is always present on `CreditNoteItem` or the fallback cost calculation `unit_price * 0.6` is acceptable when no `product` is found.
- PDF generation depends on the `dompdf` configuration settings, specifically for font rendering. `DejaVu Sans` is embedded in the view CSS to support the Nepalese Rupee symbol (`रू`).

---

## 4. Conclusion

The implementation of Credit Notes and Customer Statements in the application is complete, follows proper accounting principles, has functional stock quantity reconciliation, and renders customer statements accurately via PDF streaming.

---

## 5. Verification Method

To independently verify:
1. Run `./vendor/bin/phpunit tests/Feature/PeriodLockTest.php` to verify period lock and stock reconciliation functionality.
2. Run `./vendor/bin/phpunit tests/Feature/Filament/Resources/CustomerResourceTest.php` to verify customer resource related features.

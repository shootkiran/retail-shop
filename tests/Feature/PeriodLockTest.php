<?php

namespace Tests\Feature;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalLine;
use App\Models\Accounting\VendorBill;
use App\Models\Accounting\VendorBillItem;
use App\Models\Accounting\CreditNote;
use App\Models\Accounting\CreditNoteItem;
use App\Models\ProductItem;
use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\Sale;
use App\Services\Accounting\JournalEntryService;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodLockTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        // Delete any existing businesses to avoid tenant scoping mismatches in testing
        Business::query()->delete();

        // Create business and clear CurrentBusiness cache
        $this->business = Business::create([
            'name' => 'Lock Test Business',
            'country' => 'Nepal',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
            'currency_symbol' => 'रू',
            'is_active' => true,
        ]);

        $this->business->refresh();
        app(CurrentBusiness::class)->clear();
    }

    private function getSettings(): BusinessSetting
    {
        return BusinessSetting::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->first() ?? BusinessSetting::create([
                'business_id' => $this->business->id,
                'country' => 'Nepal',
                'timezone' => 'Asia/Kathmandu',
                'currency_code' => 'NPR',
                'currency_symbol' => 'रू',
            ]);
    }

    public function test_creating_journal_entry_on_or_before_lock_date_fails(): void
    {
        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        // Attempting to create on lock date throws RuntimeException
        $this->expectException(\RuntimeException::class);
        JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-06-01',
            'reference' => 'REF-01',
            'description' => 'Entry on lock date',
        ]);
    }

    public function test_creating_journal_entry_before_lock_date_fails(): void
    {
        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        // Attempting to create before lock date throws RuntimeException
        $this->expectException(\RuntimeException::class);
        JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-05-15',
            'reference' => 'REF-02',
            'description' => 'Entry before lock date',
        ]);
    }

    public function test_creating_journal_entry_after_lock_date_succeeds(): void
    {
        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        // Attempting to create after lock date succeeds
        $entry = JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-06-02',
            'reference' => 'REF-03',
            'description' => 'Entry after lock date',
        ]);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertEquals('2026-06-02', $entry->fresh()->entry_date->format('Y-m-d'));
    }

    public function test_updating_journal_entry_on_or_before_lock_date_fails(): void
    {
        // 1. Create entry when there is no lock date
        $entry = JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-05-15',
            'reference' => 'REF-04',
            'description' => 'Initial entry',
        ]);

        // 2. Set the lock date
        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        // 3. Try to update a description (modifying an entry that falls on or before lock date)
        $this->expectException(\RuntimeException::class);
        $entry->update([
            'description' => 'Modified description',
        ]);
    }

    public function test_updating_journal_entry_date_from_locked_to_unlocked_fails(): void
    {
        // 1. Create entry when there is no lock date
        $entry = JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-05-15',
            'reference' => 'REF-05',
            'description' => 'Initial entry',
        ]);

        // 2. Set the lock date
        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        // 3. Try to change date from locked to unlocked date
        $this->expectException(\RuntimeException::class);
        $entry->update([
            'entry_date' => '2026-06-05',
        ]);
    }

    public function test_updating_journal_entry_date_from_unlocked_to_locked_fails(): void
    {
        // 1. Set the lock date
        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        // 2. Create entry after the lock date (succeeds)
        $entry = JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-06-05',
            'reference' => 'REF-06',
            'description' => 'Initial entry',
        ]);

        // 3. Try to change date from unlocked to locked date
        $this->expectException(\RuntimeException::class);
        $entry->update([
            'entry_date' => '2026-05-20',
        ]);
    }

    public function test_updating_journal_entry_after_lock_date_succeeds(): void
    {
        // 1. Set the lock date
        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        // 2. Create entry after the lock date
        $entry = JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-06-05',
            'reference' => 'REF-07',
            'description' => 'Initial entry',
        ]);

        // 3. Try to update a field (succeeds)
        $entry->update([
            'description' => 'Updated description',
        ]);

        $this->assertEquals('Updated description', $entry->fresh()->description);

        // 4. Try to change entry date to another unlocked date (succeeds)
        $entry->update([
            'entry_date' => '2026-06-06',
        ]);

        $this->assertEquals('2026-06-06', $entry->fresh()->entry_date->format('Y-m-d'));
    }

    public function test_deleting_journal_entry_on_or_before_lock_date_fails(): void
    {
        // 1. Create entry when there is no lock date
        $entry = JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-05-15',
            'reference' => 'REF-08',
            'description' => 'Initial entry',
        ]);

        // 2. Set the lock date
        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        // 3. Try to delete the locked entry (fails)
        $this->expectException(\RuntimeException::class);
        $entry->delete();
    }

    public function test_deleting_journal_entry_after_lock_date_succeeds(): void
    {
        // 1. Set the lock date
        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        // 2. Create entry after the lock date
        $entry = JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-06-05',
            'reference' => 'REF-09',
            'description' => 'Initial entry',
        ]);

        // 3. Try to delete the entry (succeeds)
        $result = $entry->delete();

        $this->assertTrue($result);
        $this->assertDatabaseMissing('journal_entries', [
            'id' => $entry->id,
        ]);
    }

    public function test_modifying_journal_line_directly_on_locked_journal_entry_fails(): void
    {
        $entry = JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-05-15',
            'reference' => 'REF-LINE-01',
            'description' => 'Entry for line test',
        ]);

        $service = app(JournalEntryService::class);
        $account = $service->getOrCreateAccount($this->business, 'asset', 'Receivables', '1110', 'Accounts Receivable');

        $line = JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => 100.00,
            'credit' => 0.00,
        ]);

        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        $this->expectException(\RuntimeException::class);
        $line->update([
            'debit' => 200.00,
        ]);
    }

    public function test_deleting_journal_line_directly_on_locked_journal_entry_fails(): void
    {
        $entry = JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-05-15',
            'reference' => 'REF-LINE-02',
            'description' => 'Entry for line test',
        ]);

        $service = app(JournalEntryService::class);
        $account = $service->getOrCreateAccount($this->business, 'asset', 'Receivables', '1110', 'Accounts Receivable');

        $line = JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => 100.00,
            'credit' => 0.00,
        ]);

        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        $this->expectException(\RuntimeException::class);
        $line->delete();
    }

    public function test_deleting_sale_inside_locked_period_fails(): void
    {
        $sale = Sale::factory()->create([
            'business_id' => $this->business->id,
            'sold_at' => '2026-05-15',
        ]);

        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        $this->expectException(\RuntimeException::class);
        $sale->delete();
    }

    public function test_modifying_sale_date_from_unlocked_to_locked_fails(): void
    {
        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        $sale = Sale::factory()->create([
            'business_id' => $this->business->id,
            'sold_at' => '2026-06-05',
        ]);

        $this->expectException(\RuntimeException::class);
        $sale->update([
            'sold_at' => '2026-05-20',
        ]);
    }

    public function test_modifying_sale_date_from_locked_to_unlocked_fails(): void
    {
        $sale = Sale::factory()->create([
            'business_id' => $this->business->id,
            'sold_at' => '2026-05-15',
        ]);

        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        $this->expectException(\RuntimeException::class);
        $sale->update([
            'sold_at' => '2026-06-05',
        ]);
    }

    public function test_date_comparison_is_timezone_and_time_portion_safe(): void
    {
        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        $this->expectException(\RuntimeException::class);
        JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-06-01 12:00:00',
            'reference' => 'REF-TIME-01',
            'description' => 'Time-portion check',
        ]);
    }

    public function test_sale_date_comparison_is_time_portion_safe(): void
    {
        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        $this->expectException(\RuntimeException::class);
        Sale::factory()->create([
            'business_id' => $this->business->id,
            'sold_at' => '2026-06-01 12:00:00',
        ]);
    }

    public function test_changing_journal_line_parent_checks_both_locks(): void
    {
        // 1. Create two entries
        $unlockedEntry = JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-06-05',
            'reference' => 'UNLOCKED-JE',
            'description' => 'Unlocked Entry',
        ]);

        $lockedEntry = JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-05-15',
            'reference' => 'LOCKED-JE',
            'description' => 'Locked Entry',
        ]);

        $service = app(JournalEntryService::class);
        $account = $service->getOrCreateAccount($this->business, 'asset', 'Receivables', '1110', 'Accounts Receivable');

        $line = JournalLine::create([
            'journal_entry_id' => $unlockedEntry->id,
            'account_id' => $account->id,
            'debit' => 100.00,
            'credit' => 0.00,
        ]);

        // 2. Lock the period
        $settings = $this->getSettings();
        $settings->update([
            'period_lock_date' => '2026-06-01',
        ]);

        // 3. Try moving from unlocked parent to locked parent (fails due to new parent lock)
        try {
            $line->update(['journal_entry_id' => $lockedEntry->id]);
            $this->fail('Expected exception not thrown when moving to a locked journal entry.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // 4. Reverse scenario: Move from locked parent to unlocked parent (fails due to original parent lock)
        // Disable lock temporarily to place line on the locked entry
        $settings->update(['period_lock_date' => null]);
        $line->update(['journal_entry_id' => $lockedEntry->id]);

        // Lock again
        $settings->update(['period_lock_date' => '2026-06-01']);

        try {
            $line->update(['journal_entry_id' => $unlockedEntry->id]);
            $this->fail('Expected exception not thrown when moving from a locked journal entry.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }
    }

    public function test_changing_sale_item_parent_checks_both_locks(): void
    {
        $unlockedSale = Sale::factory()->create([
            'business_id' => $this->business->id,
            'sold_at' => '2026-06-05',
        ]);

        $lockedSale = Sale::factory()->create([
            'business_id' => $this->business->id,
            'sold_at' => '2026-05-15',
        ]);

        $product = \App\Models\ProductItem::create([
            'name' => 'Test Product',
            'sku' => 'PROD-S1',
            'barcode' => '1234567890123',
            'unit_cost' => 50,
            'unit_price' => 100,
            'tax_rate' => 0,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $item = \App\Models\SaleItem::create([
            'business_id' => $this->business->id,
            'sale_id' => $unlockedSale->id,
            'product_item_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $settings = $this->getSettings();
        $settings->update(['period_lock_date' => '2026-06-01']);

        // 1. Move to locked sale fails
        try {
            $item->update(['sale_id' => $lockedSale->id]);
            $this->fail('Expected exception not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // 2. Move from locked sale fails
        $settings->update(['period_lock_date' => null]);
        $item->update(['sale_id' => $lockedSale->id]);
        $settings->update(['period_lock_date' => '2026-06-01']);

        try {
            $item->update(['sale_id' => $unlockedSale->id]);
            $this->fail('Expected exception not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }
    }

    public function test_changing_purchase_item_parent_checks_both_locks(): void
    {
        $unlockedPurchase = \App\Models\Purchase::factory()->create([
            'business_id' => $this->business->id,
            'purchased_at' => '2026-06-05',
        ]);

        $lockedPurchase = \App\Models\Purchase::factory()->create([
            'business_id' => $this->business->id,
            'purchased_at' => '2026-05-15',
        ]);

        $product = \App\Models\ProductItem::create([
            'name' => 'Test Product',
            'sku' => 'PROD-P1',
            'barcode' => '1234567890124',
            'unit_cost' => 50,
            'unit_price' => 100,
            'tax_rate' => 0,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $item = \App\Models\PurchaseItem::create([
            'business_id' => $this->business->id,
            'purchase_id' => $unlockedPurchase->id,
            'product_item_id' => $product->id,
            'quantity' => 1,
            'unit_cost' => 50,
        ]);

        $settings = $this->getSettings();
        $settings->update(['period_lock_date' => '2026-06-01']);

        // 1. Move to locked purchase fails
        try {
            $item->update(['purchase_id' => $lockedPurchase->id]);
            $this->fail('Expected exception not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // 2. Move from locked purchase fails
        $settings->update(['period_lock_date' => null]);
        $item->update(['purchase_id' => $lockedPurchase->id]);
        $settings->update(['period_lock_date' => '2026-06-01']);

        try {
            $item->update(['purchase_id' => $unlockedPurchase->id]);
            $this->fail('Expected exception not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }
    }

    public function test_changing_vendor_bill_item_parent_checks_both_locks(): void
    {
        $vendor = \App\Models\Vendor::create([
            'name' => 'Test Vendor',
            'email' => 'vendor@test.com',
            'phone' => '12345678',
        ]);

        $unlockedBill = \App\Models\Accounting\VendorBill::create([
            'business_id' => $this->business->id,
            'vendor_id' => $vendor->id,
            'status' => 'draft',
            'bill_date' => '2026-06-05',
            'due_date' => '2026-06-15',
        ]);

        $lockedBill = \App\Models\Accounting\VendorBill::create([
            'business_id' => $this->business->id,
            'vendor_id' => $vendor->id,
            'status' => 'draft',
            'bill_date' => '2026-05-15',
            'due_date' => '2026-06-15',
        ]);

        $product = \App\Models\ProductItem::create([
            'name' => 'Test Product',
            'sku' => 'PROD-V1',
            'barcode' => '1234567890125',
            'unit_cost' => 50,
            'unit_price' => 100,
            'tax_rate' => 0,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $item = \App\Models\Accounting\VendorBillItem::create([
            'vendor_bill_id' => $unlockedBill->id,
            'product_item_id' => $product->id,
            'quantity' => 1,
            'unit_cost' => 50,
        ]);

        $settings = $this->getSettings();
        $settings->update(['period_lock_date' => '2026-06-01']);

        // 1. Move to locked bill fails
        try {
            $item->update(['vendor_bill_id' => $lockedBill->id]);
            $this->fail('Expected exception not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // 2. Move from locked bill fails
        $settings->update(['period_lock_date' => null]);
        $item->update(['vendor_bill_id' => $lockedBill->id]);
        $settings->update(['period_lock_date' => '2026-06-01']);

        try {
            $item->update(['vendor_bill_id' => $unlockedBill->id]);
            $this->fail('Expected exception not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }
    }

    public function test_changing_credit_note_item_parent_checks_both_locks(): void
    {
        $customer = \App\Models\Customer::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'phone' => '12345678',
        ]);

        $unlockedNote = \App\Models\Accounting\CreditNote::create([
            'business_id' => $this->business->id,
            'customer_id' => $customer->id,
            'refunded_at' => '2026-06-05',
        ]);

        $lockedNote = \App\Models\Accounting\CreditNote::create([
            'business_id' => $this->business->id,
            'customer_id' => $customer->id,
            'refunded_at' => '2026-05-15',
        ]);

        $product = \App\Models\ProductItem::create([
            'name' => 'Test Product',
            'sku' => 'PROD-C1',
            'barcode' => '1234567890126',
            'unit_cost' => 50,
            'unit_price' => 100,
            'tax_rate' => 0,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $item = \App\Models\Accounting\CreditNoteItem::create([
            'credit_note_id' => $unlockedNote->id,
            'product_item_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $settings = $this->getSettings();
        $settings->update(['period_lock_date' => '2026-06-01']);

        // 1. Move to locked note fails
        try {
            $item->update(['credit_note_id' => $lockedNote->id]);
            $this->fail('Expected exception not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // 2. Move from locked note fails
        $settings->update(['period_lock_date' => null]);
        $item->update(['credit_note_id' => $lockedNote->id]);
        $settings->update(['period_lock_date' => '2026-06-01']);

        try {
            $item->update(['credit_note_id' => $unlockedNote->id]);
            $this->fail('Expected exception not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }
    }

    public function test_editing_vendor_bill_item_updates_totals_and_syncs_journal(): void
    {
        $vendor = \App\Models\Vendor::create([
            'name' => 'Test Vendor',
            'email' => 'vendor@test.com',
            'phone' => '12345678',
        ]);

        $bill = \App\Models\Accounting\VendorBill::create([
            'business_id' => $this->business->id,
            'vendor_id' => $vendor->id,
            'status' => 'posted',
            'bill_date' => '2026-06-05',
            'due_date' => '2026-06-15',
        ]);

        $product = \App\Models\ProductItem::create([
            'name' => 'Test Product',
            'sku' => 'PROD-V2',
            'barcode' => '1234567890127',
            'unit_cost' => 50,
            'unit_price' => 100,
            'tax_rate' => 0,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $item = \App\Models\Accounting\VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'product_item_id' => $product->id,
            'quantity' => 2,
            'unit_cost' => 50,
        ]);

        // Verify totals on bill
        $bill->refresh();
        $this->assertEquals(100.00, (float) $bill->grand_total);

        // Verify Journal Entry exists and has correct total
        $je = JournalEntry::where('source_type', $bill->getMorphClass())
            ->where('source_id', $bill->id)
            ->first();
        $this->assertNotNull($je);
        $this->assertEquals(100.00, (float) $je->lines()->sum('debit'));

        // Update item quantity
        $item->update(['quantity' => 3]);

        $bill->refresh();
        $this->assertEquals(150.00, (float) $bill->grand_total);

        // Verify Journal Entry updated
        $je = JournalEntry::where('source_type', $bill->getMorphClass())
            ->where('source_id', $bill->id)
            ->first();
        $this->assertNotNull($je);
        $this->assertEquals(150.00, (float) $je->lines()->sum('debit'));
    }

    public function test_editing_credit_note_item_updates_totals_and_syncs_journal(): void
    {
        $customer = \App\Models\Customer::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'phone' => '12345678',
        ]);

        $note = \App\Models\Accounting\CreditNote::create([
            'business_id' => $this->business->id,
            'customer_id' => $customer->id,
            'refunded_at' => '2026-06-05',
        ]);

        $product = \App\Models\ProductItem::create([
            'name' => 'Test Product',
            'sku' => 'PROD-C2',
            'barcode' => '1234567890128',
            'unit_cost' => 50,
            'unit_price' => 100,
            'tax_rate' => 0,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $item = \App\Models\Accounting\CreditNoteItem::create([
            'credit_note_id' => $note->id,
            'product_item_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        // Verify totals on note
        $note->refresh();
        $this->assertEquals(200.00, (float) $note->grand_total);

        // Verify Journal Entry exists and has correct total
        $je = JournalEntry::where('source_type', $note->getMorphClass())
            ->where('source_id', $note->id)
            ->first();
        $this->assertNotNull($je);
        $this->assertEquals(200.00, (float) $je->lines()->sum('debit'));

        // Update item unit price
        $item->update(['unit_price' => 80]);

        $note->refresh();
        $this->assertEquals(160.00, (float) $note->grand_total);

        // Verify Journal Entry updated
        $je = JournalEntry::where('source_type', $note->getMorphClass())
            ->where('source_id', $note->id)
            ->first();
        $this->assertNotNull($je);
        $this->assertEquals(160.00, (float) $je->lines()->sum('debit'));
    }

    public function test_updating_locked_record_date_to_unlocked_date_fails_and_subsequent_delete_fails(): void
    {
        $settings = $this->getSettings();
        $settings->update(['period_lock_date' => null]);

        $entry = JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-05-15',
            'reference' => 'REF-LOCKED',
            'description' => 'Locked entry',
        ]);

        $settings->update(['period_lock_date' => '2026-06-01']);

        // Attempting to update a locked record's date to an unlocked date must fail
        try {
            $entry->update(['entry_date' => '2026-06-15']);
            $this->fail('Expected RuntimeException when updating a locked record.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // Verify it was not updated
        $entry->refresh();
        $this->assertEquals('2026-05-15', $entry->entry_date->toDateString());

        // Trying to delete it in the same context must fail
        try {
            $entry->delete();
            $this->fail('Expected RuntimeException when deleting a locked record.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // Verify it was not deleted
        $this->assertTrue($entry->exists);
    }

    public function test_updating_locked_sale_to_unlocked_date_fails_and_subsequent_delete_fails(): void
    {
        $settings = $this->getSettings();
        $settings->update(['period_lock_date' => null]);

        $sale = Sale::create([
            'business_id' => $this->business->id,
            'sold_at' => '2026-05-15',
            'reference' => 'SALE-LOCKED',
            'subtotal' => 100,
            'tax_total' => 0,
            'discount_total' => 0,
            'grand_total' => 100,
        ]);

        $settings->update(['period_lock_date' => '2026-06-01']);

        // Attempting to update a locked sale's date to an unlocked date must fail.
        try {
            $sale->update(['sold_at' => '2026-06-15']);
            $this->fail('Expected RuntimeException when updating a locked sale.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // Verify not updated
        $sale->refresh();
        $this->assertEquals('2026-05-15', $sale->sold_at->toDateString());

        // Trying to delete it in the same context must fail.
        try {
            $sale->delete();
            $this->fail('Expected RuntimeException when deleting a locked sale.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // Verify not deleted
        $this->assertTrue($sale->exists);
    }

    public function test_child_model_deletion_bypass_is_blocked(): void
    {
        $settings = $this->getSettings();
        $settings->update(['period_lock_date' => null]);

        // Create locked parent
        $lockedParent = JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-05-15',
            'reference' => 'PARENT-LOCKED',
        ]);

        // Create unlocked parent
        $unlockedParent = JournalEntry::create([
            'business_id' => $this->business->id,
            'entry_date' => '2026-06-15',
            'reference' => 'PARENT-UNLOCKED',
        ]);

        $service = app(JournalEntryService::class);
        $account = $service->getOrCreateAccount($this->business, 'asset', 'Receivables', '1110', 'Accounts Receivable');

        // Create child under locked parent
        $child = JournalLine::create([
            'journal_entry_id' => $lockedParent->id,
            'account_id' => $account->id,
            'debit' => 100,
            'credit' => 0,
        ]);

        // Lock period
        $settings->update(['period_lock_date' => '2026-06-01']);

        // Pollute child in memory by changing parent ID to unlocked parent
        $child->journal_entry_id = $unlockedParent->id;

        // Try to delete the child. It should query the database/original state for the locked parent and fail.
        try {
            $child->delete();
            $this->fail('Expected RuntimeException on child deletion due to locked parent.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('locked fiscal period', $e->getMessage());
        }

        // Verify child still exists in DB
        $this->assertDatabaseHas('journal_lines', ['id' => $child->id]);
    }

    public function test_inventory_stock_reconciliation_on_updating_vendor_bill_item(): void
    {
        $vendor = \App\Models\Vendor::create([
            'business_id' => $this->business->id,
            'name' => 'Test Vendor',
        ]);

        $bill = \App\Models\Accounting\VendorBill::create([
            'business_id' => $this->business->id,
            'vendor_id' => $vendor->id,
            'bill_date' => '2026-06-15',
            'due_date' => '2026-06-25',
        ]);

        $product1 = \App\Models\ProductItem::create([
            'name' => 'Prod 1',
            'sku' => 'P1',
            'barcode' => '1111111111111',
            'unit_cost' => 10,
            'unit_price' => 20,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $product2 = \App\Models\ProductItem::create([
            'name' => 'Prod 2',
            'sku' => 'P2',
            'barcode' => '2222222222222',
            'unit_cost' => 15,
            'unit_price' => 30,
            'stock_quantity' => 200,
            'is_active' => true,
        ]);

        // Create item: increments stock of product1 by 10 (100 -> 110)
        $item = \App\Models\Accounting\VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'product_item_id' => $product1->id,
            'quantity' => 10,
            'unit_cost' => 10,
        ]);

        $product1->refresh();
        $this->assertEquals(110.0, (float) $product1->stock_quantity);

        // Update item quantity: delta +5 (10 -> 15). Stock should be 110 + 5 = 115
        $item->update(['quantity' => 15]);

        $product1->refresh();
        $this->assertEquals(115.0, (float) $product1->stock_quantity);

        // Update item quantity: delta -8 (15 -> 7). Stock should be 115 - 8 = 107
        $item->update(['quantity' => 7]);

        $product1->refresh();
        $this->assertEquals(107.0, (float) $product1->stock_quantity);

        // Update product item: product1 should decrement by 7 (back to 100), product2 should increment by 20 (200 -> 220)
        $item->update([
            'product_item_id' => $product2->id,
            'quantity' => 20,
        ]);

        $product1->refresh();
        $product2->refresh();
        $this->assertEquals(100.0, (float) $product1->stock_quantity);
        $this->assertEquals(220.0, (float) $product2->stock_quantity);
    }

    public function test_inventory_stock_reconciliation_on_updating_credit_note_item(): void
    {
        $customer = \App\Models\Customer::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'phone' => '12345678',
        ]);

        $note = \App\Models\Accounting\CreditNote::create([
            'business_id' => $this->business->id,
            'customer_id' => $customer->id,
            'refunded_at' => '2026-06-15',
        ]);

        $product1 = \App\Models\ProductItem::create([
            'name' => 'Prod 1',
            'sku' => 'P1_CN',
            'barcode' => '3333333333333',
            'unit_cost' => 10,
            'unit_price' => 20,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $product2 = \App\Models\ProductItem::create([
            'name' => 'Prod 2',
            'sku' => 'P2_CN',
            'barcode' => '4444444444444',
            'unit_cost' => 15,
            'unit_price' => 30,
            'stock_quantity' => 200,
            'is_active' => true,
        ]);

        // Create item: increments stock of product1 by 10 (100 -> 110)
        $item = \App\Models\Accounting\CreditNoteItem::create([
            'credit_note_id' => $note->id,
            'product_item_id' => $product1->id,
            'quantity' => 10,
            'unit_price' => 20,
        ]);

        $product1->refresh();
        $this->assertEquals(110.0, (float) $product1->stock_quantity);

        // Update item quantity: delta +5 (10 -> 15). Stock should be 110 + 5 = 115
        $item->update(['quantity' => 15]);

        $product1->refresh();
        $this->assertEquals(115.0, (float) $product1->stock_quantity);

        // Update item quantity: delta -8 (15 -> 7). Stock should be 115 - 8 = 107
        $item->update(['quantity' => 7]);

        $product1->refresh();
        $this->assertEquals(107.0, (float) $product1->stock_quantity);

        // Update product item: product1 should decrement by 7 (back to 100), product2 should increment by 20 (200 -> 220)
        $item->update([
            'product_item_id' => $product2->id,
            'quantity' => 20,
        ]);

        $product1->refresh();
        $product2->refresh();
        $this->assertEquals(100.0, (float) $product1->stock_quantity);
        $this->assertEquals(220.0, (float) $product2->stock_quantity);
    }
}

<?php

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\StockAdjustments;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\FinancialEntry;
use App\Models\ProductCategory;
use App\Models\ProductItem;
use App\Models\Unit;
use App\Models\Vendor;
use App\Support\CurrentBusiness;
use Livewire\Livewire;
use Tests\Feature\Filament\FilamentTestCase;

class StockAdjustmentsPageTest extends FilamentTestCase
{
    public function test_stock_adjustments_page_can_render(): void
    {
        Livewire::test(StockAdjustments::class)->assertOk();
    }

    public function test_stock_adjustment_updates_inventory_and_posts_daybook_entries(): void
    {
        $business = Business::create([
            'name' => 'Stock Adjustment Business',
            'country' => 'Nepal',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
            'currency_symbol' => 'रू',
            'is_active' => true,
        ]);

        $this->user->forceFill([
            'current_business_id' => $business->id,
            'office_type' => 'back_office',
            'is_active' => true,
        ])->save();

        $this->user->businesses()->attach($business->id, [
            'role' => 'admin',
            'office_type' => 'back_office',
            'is_active' => true,
        ]);

        app(CurrentBusiness::class)->clear();

        BankAccount::create([
            'name' => 'Operating Bank',
            'bank_name' => 'Local Bank',
            'account_number' => '1234567890',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        $category = ProductCategory::create([
            'name' => 'Food',
            'slug' => 'food',
            'description' => 'Food items',
        ]);

        $vendor = Vendor::create([
            'name' => 'Food Vendor',
            'email' => 'food@example.test',
            'phone' => '9800000002',
            'contact_person' => 'Food Contact',
            'address' => 'Vendor Street',
        ]);

        $dozen = Unit::create([
            'name' => 'Dozen',
            'symbol' => 'dozen',
            'multiplier_to_base' => 12,
            'is_base' => false,
            'is_active' => true,
        ]);

        $product = ProductItem::create([
            'product_category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'base_unit_id' => $dozen->id,
            'name' => 'Egg Tray',
            'sku' => 'EGG-001',
            'barcode' => '1111111111111',
            'description' => 'Egg tray',
            'unit_cost' => 10,
            'unit_price' => 15,
            'tax_rate' => 0,
            'stock_quantity' => 24,
            'reorder_level' => 6,
            'is_active' => true,
        ]);

        Livewire::test(StockAdjustments::class)
            ->set('countedAt', now()->toDateString())
            ->set('postingMode', 'inventory_and_daybook')
            ->set('reference', 'STK-TEST-001')
            ->set('lines.'.$product->getKey().'.counted_quantity', 1.5)
            ->set('lines.'.$product->getKey().'.notes', 'Counted on shelf')
            ->call('saveAdjustment')
            ->assertOk();

        $product->refresh();

        $this->assertSame(18.0, round((float) $product->stock_quantity, 4));

        $this->assertDatabaseHas('stock_adjustment_batches', [
            'reference' => 'STK-TEST-001',
            'posting_mode' => 'inventory_and_daybook',
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('stock_adjustment_lines', [
            'counted_quantity' => 1.5,
            'counted_quantity_base' => 18.0,
            'variance_base' => -6.0,
            'variance_value' => -60.0,
        ]);

        $this->assertDatabaseHas(FinancialEntry::class, [
            'entry_type' => 'stock_adjustment_loss',
            'direction' => 'debit',
            'amount' => 60.0,
            'reference' => 'STK-TEST-001',
        ]);
    }
}

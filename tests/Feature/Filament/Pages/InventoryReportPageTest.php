<?php

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\InventoryReport;
use App\Models\Business;
use App\Models\ProductCategory;
use App\Models\ProductItem;
use App\Models\Unit;
use App\Models\Vendor;
use App\Support\CurrentBusiness;
use Livewire\Livewire;
use Tests\Feature\Filament\FilamentTestCase;

class InventoryReportPageTest extends FilamentTestCase
{
    public function test_inventory_report_page_can_render(): void
    {
        Livewire::test(InventoryReport::class)->assertOk();
    }

    public function test_inventory_summary_and_unit_display_are_correct(): void
    {
        $business = Business::create([
            'name' => 'Inventory Test Business',
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

        $category = ProductCategory::create([
            'name' => 'Beverages',
            'slug' => 'beverages',
            'description' => 'Drink products',
        ]);

        $vendor = Vendor::create([
            'name' => 'Global Supply',
            'email' => 'supplier@example.test',
            'phone' => '9800000000',
            'contact_person' => 'Supply Contact',
            'address' => 'Supply Street',
        ]);

        $dozen = Unit::create([
            'name' => 'Dozen',
            'symbol' => 'dozen',
            'multiplier_to_base' => 12,
            'is_base' => false,
            'is_active' => true,
        ]);

        ProductItem::create([
            'product_category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'base_unit_id' => $dozen->id,
            'name' => 'Egg Tray',
            'sku' => 'EGG-001',
            'barcode' => '1111111111111',
            'description' => 'Pack of eggs',
            'unit_cost' => 24,
            'unit_price' => 36,
            'tax_rate' => 0,
            'stock_quantity' => 24,
            'reorder_level' => 6,
            'is_active' => true,
        ]);

        ProductItem::create([
            'product_category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'base_unit_id' => null,
            'name' => 'Water Bottle',
            'sku' => 'WTR-001',
            'barcode' => '2222222222222',
            'description' => 'Single bottle',
            'unit_cost' => 10,
            'unit_price' => 15,
            'tax_rate' => 0,
            'stock_quantity' => 3,
            'reorder_level' => 5,
            'is_active' => true,
        ]);

        ProductItem::create([
            'product_category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'base_unit_id' => null,
            'name' => 'Out of Stock Item',
            'sku' => 'OOS-001',
            'barcode' => '3333333333333',
            'description' => 'Nothing left',
            'unit_cost' => 5,
            'unit_price' => 8,
            'tax_rate' => 0,
            'stock_quantity' => 0,
            'reorder_level' => 2,
            'is_active' => true,
        ]);

        $otherBusiness = Business::create([
            'name' => 'Other Business',
            'country' => 'Nepal',
            'timezone' => 'Asia/Kathmandu',
            'currency_code' => 'NPR',
            'currency_symbol' => 'रू',
            'is_active' => true,
        ]);

        ProductItem::withoutGlobalScopes()->create([
            'business_id' => $otherBusiness->id,
            'product_category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'base_unit_id' => null,
            'name' => 'Other Business Item',
            'sku' => 'OTH-001',
            'barcode' => '4444444444444',
            'description' => 'Should not appear',
            'unit_cost' => 99,
            'unit_price' => 120,
            'tax_rate' => 0,
            'stock_quantity' => 50,
            'reorder_level' => 1,
            'is_active' => true,
        ]);

        $page = app(InventoryReport::class);

        $summary = $page->summary;
        $rows = $page->inventoryRows;

        $this->assertSame(3, $summary['total_skus']);
        $this->assertSame(2, $summary['items_in_stock']);
        $this->assertSame(1, $summary['low_stock_items']);
        $this->assertSame(606.0, round((float) $summary['total_valuation'], 2));
        $this->assertTrue($rows->contains(fn (array $row): bool => $row['name'] === 'Egg Tray' && $row['display_stock'] === '2.00 dozen'));
        $this->assertTrue($rows->contains(fn (array $row): bool => $row['name'] === 'Water Bottle' && $row['status'] === 'low_stock'));
        $this->assertFalse($rows->contains(fn (array $row): bool => $row['name'] === 'Other Business Item'));

        Livewire::test(InventoryReport::class)
            ->assertOk()
            ->assertSee('Inventory Report')
            ->assertSee('Egg Tray')
            ->assertSee('2.00 dozen')
            ->assertSee('Water Bottle')
            ->assertDontSee('Other Business Item');
    }

    public function test_low_stock_filter_limits_rows(): void
    {
        $business = Business::create([
            'name' => 'Inventory Filter Business',
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

        $category = ProductCategory::create([
            'name' => 'Snacks',
            'slug' => 'snacks',
            'description' => 'Snack products',
        ]);

        $vendor = Vendor::create([
            'name' => 'Snack Vendor',
            'email' => 'snack@example.test',
            'phone' => '9800000001',
            'contact_person' => 'Vendor',
            'address' => 'Vendor Street',
        ]);

        ProductItem::create([
            'product_category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'base_unit_id' => null,
            'name' => 'Low Stock Item',
            'sku' => 'LOW-001',
            'barcode' => '5555555555555',
            'description' => 'Should remain visible',
            'unit_cost' => 10,
            'unit_price' => 15,
            'tax_rate' => 0,
            'stock_quantity' => 2,
            'reorder_level' => 5,
            'is_active' => true,
        ]);

        ProductItem::create([
            'product_category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'base_unit_id' => null,
            'name' => 'In Stock Item',
            'sku' => 'IN-001',
            'barcode' => '6666666666666',
            'description' => 'Should be filtered out',
            'unit_cost' => 10,
            'unit_price' => 15,
            'tax_rate' => 0,
            'stock_quantity' => 12,
            'reorder_level' => 5,
            'is_active' => true,
        ]);

        Livewire::test(InventoryReport::class)
            ->set('stockStatus', 'low_stock')
            ->assertOk()
            ->assertSee('Low Stock Item')
            ->assertDontSee('In Stock Item');
    }
}

<?php

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\InventoryCountSheet;
use App\Models\Business;
use App\Models\ProductCategory;
use App\Models\ProductItem;
use App\Models\Unit;
use App\Models\Vendor;
use App\Support\CurrentBusiness;
use Livewire\Livewire;
use Tests\Feature\Filament\FilamentTestCase;

class InventoryCountSheetPageTest extends FilamentTestCase
{
    public function test_inventory_count_sheet_page_can_render(): void
    {
        Livewire::test(InventoryCountSheet::class)->assertOk();
    }

    public function test_inventory_count_sheet_pdf_uses_current_business_inventory(): void
    {
        $business = Business::create([
            'name' => 'Count Sheet Business',
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
            'name' => 'Dairy',
            'slug' => 'dairy',
            'description' => 'Dairy products',
        ]);

        $vendor = Vendor::create([
            'name' => 'North Vendor',
            'email' => 'vendor@example.test',
            'phone' => '9800000001',
            'contact_person' => 'Vendor Contact',
            'address' => 'Vendor Street',
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
            'description' => 'Egg tray',
            'unit_cost' => 24,
            'unit_price' => 36,
            'tax_rate' => 0,
            'stock_quantity' => 24,
            'reorder_level' => 6,
            'is_active' => true,
        ]);

        $otherBusiness = Business::create([
            'name' => 'Other Count Sheet Business',
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
            'base_unit_id' => $dozen->id,
            'name' => 'Hidden Item',
            'sku' => 'HID-001',
            'barcode' => '9999999999999',
            'description' => 'Should not show',
            'unit_cost' => 5,
            'unit_price' => 8,
            'tax_rate' => 0,
            'stock_quantity' => 10,
            'reorder_level' => 1,
            'is_active' => true,
        ]);

        $page = app(InventoryCountSheet::class);

        $this->assertSame(1, $page->summary['total_skus']);
        $this->assertSame('2.00 dozen', $page->rows->first()['display_stock']);

        $this->get(route('inventory.count-sheet.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}

<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\ProductItems\Pages\CreateProductItem;
use App\Filament\Resources\ProductItems\Pages\EditProductItem;
use App\Filament\Resources\ProductItems\Pages\ListProductItems;
use App\Filament\Resources\ProductItems\ProductItemResource;
use App\Models\ProductCategory;
use App\Models\ProductItem;
use App\Models\Vendor;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;
use Tests\Feature\Filament\FilamentTestCase;

class ProductItemResourceTest extends FilamentTestCase
{
    public function test_list_page_can_render(): void
    {
        Livewire::test(ListProductItems::class)->assertOk();
    }

    public function test_can_create_product_item(): void
    {
        $category = ProductCategory::factory()->create();
        $vendor = Vendor::factory()->create();
        $itemData = ProductItem::factory()->make([
            'product_category_id' => $category->id,
            'vendor_id' => $vendor->id,
        ]);

        Livewire::test(CreateProductItem::class)
            ->fillForm([
                'name' => $itemData->name,
                'sku' => $itemData->sku,
                'barcode' => $itemData->barcode,
                'product_category_id' => $category->id,
                'vendor_id' => $vendor->id,
                'description' => $itemData->description,
                'unit_cost' => $itemData->unit_cost,
                'unit_price' => $itemData->unit_price,
                'tax_rate' => $itemData->tax_rate,
                'stock_quantity' => $itemData->stock_quantity,
                'reorder_level' => $itemData->reorder_level,
                'is_active' => $itemData->is_active,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();

        $this->assertDatabaseHas(ProductItem::class, [
            'sku' => $itemData->sku,
            'product_category_id' => $category->id,
            'vendor_id' => $vendor->id,
        ]);
    }

    public function test_can_update_product_item(): void
    {
        $item = ProductItem::factory()->create();
        $newCategory = ProductCategory::factory()->create();
        $newVendor = Vendor::factory()->create();
        $updatedData = ProductItem::factory()->make([
            'product_category_id' => $newCategory->id,
            'vendor_id' => $newVendor->id,
        ]);

        Livewire::test(EditProductItem::class, ['record' => $item->getKey()])
            ->fillForm([
                'name' => $updatedData->name,
                'sku' => $updatedData->sku,
                'barcode' => $updatedData->barcode,
                'product_category_id' => $newCategory->id,
                'vendor_id' => $newVendor->id,
                'description' => $updatedData->description,
                'unit_cost' => $updatedData->unit_cost,
                'unit_price' => $updatedData->unit_price,
                'tax_rate' => $updatedData->tax_rate,
                'stock_quantity' => $updatedData->stock_quantity,
                'reorder_level' => $updatedData->reorder_level,
                'is_active' => $updatedData->is_active,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas(ProductItem::class, [
            'id' => $item->id,
            'sku' => $updatedData->sku,
            'product_category_id' => $newCategory->id,
            'vendor_id' => $newVendor->id,
        ]);
    }

    public function test_can_delete_product_item(): void
    {
        $item = ProductItem::factory()->create();

        Livewire::test(ListProductItems::class)
            ->callTableAction(DeleteAction::class, $item);

        $this->assertModelMissing($item);
    }
}

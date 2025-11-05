<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\ProductCategories\Pages\CreateProductCategory;
use App\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategories\Pages\ListProductCategories;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Models\ProductCategory;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Filament\FilamentTestCase;

class ProductCategoryResourceTest extends FilamentTestCase
{
    public function test_list_page_can_render(): void
    {
        Livewire::test(ListProductCategories::class)->assertOk();
    }

    public function test_can_create_category(): void
    {
        $categoryData = ProductCategory::factory()->make();

        Livewire::test(CreateProductCategory::class)
            ->fillForm([
                'name' => $categoryData->name,
                'slug' => $categoryData->slug ?? Str::slug($categoryData->name),
                'description' => $categoryData->description,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();

        $this->assertDatabaseHas(ProductCategory::class, [
            'name' => $categoryData->name,
        ]);
    }

    public function test_can_update_category(): void
    {
        $category = ProductCategory::factory()->create();
        $updatedData = ProductCategory::factory()->make();

        Livewire::test(EditProductCategory::class, ['record' => $category->getKey()])
            ->fillForm([
                'name' => $updatedData->name,
                'slug' => $updatedData->slug ?? Str::slug($updatedData->name),
                'description' => $updatedData->description,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas(ProductCategory::class, [
            'id' => $category->id,
            'name' => $updatedData->name,
        ]);
    }

    public function test_can_delete_category(): void
    {
        $category = ProductCategory::factory()->create();

        Livewire::test(ListProductCategories::class)
            ->callTableAction(DeleteAction::class, $category);

        $this->assertModelMissing($category);
    }
}

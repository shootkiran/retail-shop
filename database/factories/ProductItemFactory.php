<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use App\Models\ProductItem;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductItem>
 */
class ProductItemFactory extends Factory
{
    protected $model = ProductItem::class;

    public function definition(): array
    {
        return [
            'product_category_id' => ProductCategory::factory(),
            'vendor_id' => Vendor::factory(),
            'name' => $this->faker->words(3, true),
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-#####')),
            'barcode' => $this->faker->ean13(),
            'description' => $this->faker->sentence(),
            'unit_cost' => $this->faker->randomFloat(2, 5, 500),
            'unit_price' => $this->faker->randomFloat(2, 10, 800),
            'tax_rate' => $this->faker->randomFloat(2, 0, 15),
            'stock_quantity' => $this->faker->numberBetween(0, 200),
            'reorder_level' => $this->faker->numberBetween(0, 50),
            'is_active' => true,
        ];
    }
}

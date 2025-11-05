<?php

namespace Database\Factories;

use App\Models\ProductItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 10);
        $unitPrice = $this->faker->randomFloat(2, 10, 500);
        $discount = $this->faker->randomFloat(2, 0, $unitPrice);

        return [
            'sale_id' => Sale::factory(),
            'product_item_id' => ProductItem::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discount,
            'total_amount' => max(($quantity * $unitPrice) - $discount, 0),
        ];
    }
}

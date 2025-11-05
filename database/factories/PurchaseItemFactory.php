<?php

namespace Database\Factories;

use App\Models\ProductItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 10);
        $unitCost = $this->faker->randomFloat(2, 10, 300);
        $discount = $this->faker->randomFloat(2, 0, $unitCost);

        return [
            'purchase_id' => Purchase::factory(),
            'product_item_id' => ProductItem::factory(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'discount_amount' => $discount,
            'total_amount' => max(($quantity * $unitCost) - $discount, 0),
        ];
    }
}

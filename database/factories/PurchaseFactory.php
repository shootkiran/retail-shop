<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'reference' => null,
            'status' => $this->faker->randomElement(['draft', 'ordered', 'received']),
            'total_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => $this->faker->randomFloat(2, 0, 100),
            'grand_total' => 0,
            'amount_paid' => 0,
            'amount_due' => 0,
            'purchased_at' => Carbon::now(),
            'notes' => $this->faker->sentence(),
        ];
    }
}

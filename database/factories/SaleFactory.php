<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'reference' => null,
            'status' => $this->faker->randomElement(['draft', 'completed', 'cancelled']),
            'payment_status' => $this->faker->randomElement(['pending', 'partial', 'paid']),
            'payment_type' => $this->faker->randomElement(['paid', 'credit']),
            'total_amount' => 0,
            'discount_amount' => 0,
            'order_discount' => 0,
            'tax_amount' => $this->faker->randomFloat(2, 0, 100),
            'grand_total' => 0,
            'amount_paid' => 0,
            'amount_due' => 0,
            'notes' => $this->faker->sentence(),
            'sold_at' => Carbon::now(),
        ];
    }
}

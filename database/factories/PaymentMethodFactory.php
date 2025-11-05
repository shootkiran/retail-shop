<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word() . ' Payment',
            'type' => $this->faker->randomElement(['cash', 'cheque', 'online']),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}

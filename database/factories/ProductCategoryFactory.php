<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(asText: true);

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->lexify(),
            'description' => $this->faker->sentence(),
        ];
    }
}

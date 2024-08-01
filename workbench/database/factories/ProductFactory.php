<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Product;

/**
 * @template TModel of \Workbench\App\Product
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->realText(50),
            'price' => fake()->randomFloat(2),
            'color' => fake()->colorName(),
            'status' => fake()->numberBetween(0, 20),
            'manufacturer.country' => fake()->country(),
            'is_active' => fake()->boolean(),
            'in_stock' => fake()->boolean(),
            'is_approved' => fake()->boolean(),
            'type' => fake()->realText(30),
        ];
    }
}

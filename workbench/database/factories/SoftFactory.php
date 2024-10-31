<?php

namespace Workbench\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Soft;

class SoftFactory extends Factory
{
    protected $model = Soft::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'description' => fake()->realTextBetween(100),
            'product_id' => fake()->uuid(),
            'in_stock' => fake()->numberBetween(0, 100),
            'status' => fake()->numberBetween(1, 9),
            'color' => fake()->safeColorName(),
            'is_active' => fake()->boolean(),
            'price' => fake()->randomFloat(2, 0, 2000),
            'orders' => fake()->numberBetween(0, 250),

            'created_at' => $this->faker->dateTimeBetween('-31 days'),
            'updated_at' => Carbon::now(),
            'deleted_at' => null,
        ];
    }
}

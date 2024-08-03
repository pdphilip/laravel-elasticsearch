<?php

namespace Workbench\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Product;


class ProductFactory extends Factory
{
  protected $model = Product::class;

  public function definition(): array
  {
    return [
      'name'                => fake()->name(),
      'description'         => fake()->realTextBetween(100),
      'product_id'          => fake()->uuid(),
      'in_stock'            => fake()->numberBetween(0,100),
      'status'              => fake()->numberBetween(1,9),
      'color'               => fake()->safeColorName(),
      'is_active'           => fake()->boolean(),
      'price'               => fake()->randomFloat(2, 0, 2000),
      'orders'              => fake()->numberBetween(0,250),
      'order_values'        => $this->randomArrayOfInts(),

      'manufacturer' => [
        'location' => [
          'lat' => fake()->latitude(),
          'lon' => fake()->longitude(),
        ],
        'name'     => fake()->company(),
        'country'  => fake()->country(),
        'owned_by' => [
          'name'    => fake()->name(),
          'country' => fake()->country(),
        ],
      ],
      'created_at'   => Carbon::now(),
      'updated_at'   => Carbon::now(),
      'deleted_at'   => null,
    ];
  }


  public function randomArrayOfInts()
  {
    $array = [];
    $i = 0;
    while ($i < rand(0, 50)) {
      $array[] = rand(5, 200);
      $i++;
    }

    return $array;
  }


  public function definitionUSA()
  {
    return [
      'name'         => fake()->name(),
      'product_id'   => fake()->uuid(),
      'in_stock'     => fake()->numberBetween(0,100),
      'status'       => fake()->numberBetween(1,9),
      'color'        => fake()->safeColorName(),
      'is_active'    => fake()->boolean(),
      'price'        => fake()->randomFloat(2, 0, 2000),
      'orders'       => fake()->numberBetween(0,250),
      'manufacturer' => [
        'location' => [
          'lat' => fake()->latitude(),
          'lon' => fake()->longitude(),
        ],
        'name'     => fake()->company(),
        'country'  => 'United States of America',
        'owned_by' => [
          'name'    => fake()->name(),
          'country' => fake()->country(),
        ],
      ],
      'created_at'   => Carbon::now(),
      'updated_at'   => Carbon::now(),
    ];
  }

}

<?php

namespace PDPhilip\Elasticsearch\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use PDPhilip\Elasticsearch\Tests\Models\Product;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function randomFail()
    {
        $orders = rand(0, 250);
        // if ($orders == 10) {
        //    return 'xx';
        // }

        return $orders;
    }

    public function definition(): array
    {
        $tsMs = $this->randomTsAndMs();

        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->realTextBetween(100),
            'product_id' => $this->faker->uuid(),
            'in_stock' => rand(0, 100),
            'status' => rand(1, 9),
            'color' => $this->faker->safeColorName(),
            'is_active' => $this->faker->boolean(),
            'price' => $this->faker->randomFloat(2, 0, 2000),
            'orders' => $this->randomFail(),
            'order_values' => $this->randomArrayOfInts(),
            'last_order_datetime' => $tsMs['datetime'],
            'last_order_ts' => $tsMs['ts'],
            'last_order_ms' => $tsMs['ms'],

            'manufacturer' => [
                'location' => [
                    'lat' => $this->faker->latitude(),
                    'lon' => $this->faker->longitude(),
                ],
                'name' => $this->faker->company(),
                'country' => $this->faker->country(),
                'owned_by' => [
                    'name' => $this->faker->name(),
                    'country' => $this->faker->country(),
                ],
            ],
            'datetime' => Carbon::now()->format('Y-m-d H:i:s'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    public function randomTsAndMs()
    {
        $date = Carbon::now();
        $date->subDays(rand(0, 14))->subMinutes(rand(0, 1440))->subSeconds(rand(0, 60));

        return [
            'datetime' => $date->format('Y-m-d H:i:s'),
            'ts' => $date->getTimestamp(),
            'ms' => $date->getTimestampMs(),
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
            'name' => $this->faker->name(),
            'product_id' => $this->faker->uuid(),
            'in_stock' => rand(0, 100),
            'status' => rand(1, 9),
            'color' => $this->faker->safeColorName(),
            'is_active' => $this->faker->boolean(),
            'price' => $this->faker->randomFloat(2, 0, 2000),
            'orders' => rand(0, 250),
            'manufacturer' => [
                'location' => [
                    'lat' => $this->faker->latitude(),
                    'lon' => $this->faker->longitude(),
                ],
                'name' => $this->faker->company(),
                'country' => 'United States of America',
                'owned_by' => [
                    'name' => $this->faker->name(),
                    'country' => $this->faker->country(),
                ],
            ],
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}

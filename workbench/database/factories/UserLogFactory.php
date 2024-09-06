<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Workbench\App\Models\UserLog;

class UserLogFactory extends Factory
{
    protected $model = UserLog::class;

    public function definition(): array
    {

        return [
            'title' => fake()->word(),
            'score' => fake()->word(),
            'secret' => fake()->word(),
            'code' => fake()->numberBetween(1, 5),
            'meta' => [],
            'agent' => [
                'ip' => fake()->ipv4(),
                'source' => fake()->url(),
                'method' => 'GET',
                'browser' => fake()->chrome(),
                'device' => fake()->chrome(),
                'deviceType' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
                'geo' => [
                    'lat' => fake()->latitude(),
                    'lon' => fake()->longitude(),
                ],
                'countryCode' => fake()->countryCode(),
                'city' => fake()->city(),
            ],
            'status' => fake()->numberBetween(1, 9),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}

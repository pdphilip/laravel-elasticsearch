<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\PageHit;

class PageHitFactory extends Factory
{
    protected $model = PageHit::class;

    public function definition(): array
    {
        return [
            'ip' => fake()->ipv4(),
            'page_id' => fake()->numberBetween(1, 9),
            'date' => fake()->randomElement([
                '2021-01-01',
                '2021-01-02',
                '2021-01-03',
                '2021-01-04',
                '2021-01-05',
                '2021-01-06',
                '2021-01-07',
                '2021-01-08',
                '2021-01-09',
                '2021-01-10',
            ]),
        ];
    }
}

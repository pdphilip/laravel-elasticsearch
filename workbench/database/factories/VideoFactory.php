<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Video;

class VideoFactory extends Factory
{
    protected $model = Video::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}

<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\EsPhoto;

class EsPhotoFactory extends Factory
{
    protected $model = EsPhoto::class;

    public function definition(): array
    {
        return [
            'url' => fake()->imageUrl(),
        ];
    }
}

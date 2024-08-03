<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Photo;

class PhotoFactory extends Factory
{
    protected $model = Photo::class;

    public function definition()
    {
        return [
            'url' => $this->faker->imageUrl,
            'photoable_id' => null, // To be set when creating instances
            'photoable_type' => null, // To be set when creating instances
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

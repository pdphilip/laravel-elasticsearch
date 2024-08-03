<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Avatar;

class AvatarFactory extends Factory
{
    protected $model = Avatar::class;

    public function definition()
    {
        return [
            'url' => $this->faker->imageUrl,
            'imageable_id' => null, // To be set when creating instances
            'imageable_type' => null, // To be set when creating instances
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

<?php

  namespace Workbench\Database\Factories;

  use Illuminate\Database\Eloquent\Factories\Factory;
  use Illuminate\Support\Carbon;
  use Workbench\App\Models\UserProfile;

  class UserProfileFactory extends Factory
  {
    protected $model = UserProfile::class;

    public function definition(): array
    {
      return [
        'twitter'    => fake()->word(),
        'facebook'   => fake()->word(),
        'address'    => fake()->address(),
        'timezone'   => fake()->timezone(),
        'status'     => fake()->randomNumber(),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
      ];
    }
  }

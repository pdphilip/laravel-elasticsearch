<?php

  namespace Workbench\Database\Factories;

  use Illuminate\Database\Eloquent\Factories\Factory;
  use Illuminate\Support\Carbon;
  use Workbench\App\Models\Company;

  class CompanyFactory extends Factory
  {
    protected $model = Company::class;

    public function definition(): array
    {
      return [
        'name'       => fake()->company(),
        'status'     => fake()->randomNumber(),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
      ];
    }

  }

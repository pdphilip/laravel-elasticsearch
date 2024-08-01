<?php

  namespace Workbench\Database\Factories;
  use Illuminate\Database\Eloquent\Factories\Factory;
  use Illuminate\Support\Carbon;
  use Workbench\App\Models\CompanyLog;

  class CompanyLogFactory extends Factory
  {
    protected $model = CompanyLog::class;

    public function definition(): array
    {
      return [
        'company_id' => '',
        'title'      => fake()->word(),
        'desc'       => fake()->sentence(),
        'status'     => fake()->randomNumber(),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
      ];
    }
  }

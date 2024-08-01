<?php

  namespace Workbench\Database\Factories;

  use Illuminate\Database\Eloquent\Factories\Factory;
  use Illuminate\Support\Carbon;
  use Workbench\App\Models\ClientLog;

  class ClientLogFactory extends Factory
  {
    protected $model = ClientLog::class;

    public function definition(): array
    {
      return [
        'client_id'  => '',
        'title'      => fake()->word(),
        'desc'       => fake()->sentence(),
        'status'     => fake()->randomNumber(),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
      ];
    }
  }

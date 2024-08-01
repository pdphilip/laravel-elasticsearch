<?php

  namespace Workbench\Database\Factories;

  use Illuminate\Database\Eloquent\Factories\Factory;
  use Illuminate\Support\Carbon;
  use Workbench\App\Models\Client;

  class ClientFactory extends Factory
  {
    protected $model = Client::class;

    public function definition(): array
    {
      return [
        'company_id' => '',
        'name'       => fake()->name(),
        'status'     => fake()->randomNumber(),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
      ];
    }
  }

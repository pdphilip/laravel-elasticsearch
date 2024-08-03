<?php

  namespace Workbench\Database\Factories;
  use Illuminate\Database\Eloquent\Factories\Factory;
  use Illuminate\Support\Carbon;
  use Workbench\App\Models\ClientProfile;
  use Workbench\App\Models\Company;

  class ClientProfileFactory extends Factory
  {
    protected $model = ClientProfile::class;

    public function definition(): array
    {
      return [
        'client_id'     => '',
        'company_id' => function () {
          return Company::factory()->create()->_id;
        },
        'contact_name'  => fake()->name(),
        'contact_email' => fake()->email(),
        'website'       => fake()->url(),
        'status'        => fake()->randomNumber(),
        'created_at'    => Carbon::now(),
        'updated_at'    => Carbon::now(),
      ];
    }
  }

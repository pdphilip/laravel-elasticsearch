<?php

  declare(strict_types=1);

  namespace Workbench\Database\Factories;

  use Illuminate\Database\Eloquent\Factories\Factory;
  use Workbench\App\Models\StaticPage;

  /**
   * Factory for StaticPage model.
   *
   * @extends Factory<StaticPage>
   */
  class StaticPageFactory extends Factory
  {
    protected $model = StaticPage::class;

    /**
     * Defines the default state for the BlogPost model.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
      return [
        'title' => fake()->sentence(),
        'content' => fake()->text(),
      ];
    }
  }

<?php

  declare(strict_types=1);

  namespace Workbench\Database\Factories;

  use Carbon\Carbon;
  use Illuminate\Database\Eloquent\Factories\Factory;
  use Workbench\App\Models\BlogPost;

  /**
   * Factory for BlogPost model.
   *
   * @extends Factory<BlogPost>
   */
  class BlogPostFactory extends Factory
  {
    protected $model = BlogPost::class;

    /**
     * Generates an array of random comments.
     *
     * @param int $count The number of comments to generate.
     * @return array An array of comment data.
     */
    public function generateComments(int $count): array
    {
      return collect(range(1, $count))->map(function () {
        return [
          'name' => fake()->name(),
          'comment' => fake()->text(),
          'country' => fake()->country(),
          'likes' => fake()->numberBetween(0, 10),
        ];
      })->all();
    }

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
        'comments' => $this->generateComments(fake()->numberBetween(5, 20)),
        'status' => fake()->numberBetween(1, 5),
        'active' => fake()->boolean(),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
      ];
    }
  }

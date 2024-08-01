<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\BlogPost;

/**
 * @template TModel of \Workbench\App\Product
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function generateRandomCountry()
    {
        $countries = [
            'USA',
            'UK',
            'Canada',
            'Australia',
            'Germany',
            'France',
            'Netherlands',
            'Austria',
            'Switzerland',
            'Sweden',
            'Norway',
            'Denmark',
            'Finland',
            'Belgium',
            'Italy',
            'Spain',
            'Portugal',
            'Greece',
            'Ireland',
            'Poland',
            'Peru',
        ];

        return $countries[rand(0, count($countries) - 1)];
    }

    public function generateComments($count)
    {
        $comments = [];
        for ($i = 0; $i < $count; $i++) {
            $comment = [
                'name' => fake()->name(),
                'comment' => fake()->text(),
                'country' => fake()->country(),
                'likes' => fake()->numberBetween(0, 10),

            ];
            $comments[] = $comment;
        }

        return $comments;
    }

    public function definition(): array
    {

        return [
            'title' => fake()->word(),
            'content' => fake()->word(),
            'comments' => $this->generateComments(fake()->numberBetween(5, 20)),
            'status' => fake()->numberBetween(1, 5),
            'active' => fake()->boolean(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}

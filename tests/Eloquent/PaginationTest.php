<?php

declare(strict_types=1);

  use Carbon\Carbon;
  use Workbench\App\Models\Post;

test('posts are paginated using search_after', function () {

  Post::truncate();

  //Generate a massive amount of data to paginate over.
  $collectionToInsert = collect([]);
  $numberOfEntries = 25000;
  for ($i = 1; $i <= $numberOfEntries; $i++) {
    $collectionToInsert->push([
                                'title' => fake()->name(),
                                'slug' => fake()->uuid(),
                                'content' => fake()->realTextBetween(5, 15),
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                              ]);
  }

  foreach ($collectionToInsert as $count => $post) {
    Post::createWithoutRefresh($post);
  }
  sleep(3);

  $perPage = 100;
  $totalFetched = 0;
  $totalProducts = Post::count();

  // Fetch the first page of posts
  $paginator = Post::orderBy('slug.keyword')->elasticPaginate($perPage)->withQueryString();

    do {
      // Count the number of posts fetched in the current page
      $totalFetched += $paginator->count();

      // Move to the next page if possible
      if ($paginator->hasMorePages()) {
        $cursor = $paginator->nextCursor();
        $paginator = Post::orderBy('slug.keyword')->elasticPaginate($perPage, ['*'], 'cursor', $cursor)->withQueryString();
      }
    } while ($paginator->hasMorePages());

    // Include the last page count if not empty
    $totalFetched += $paginator->count();

    // Check if all products were fetched
  expect($totalFetched)->toEqual($totalProducts);

})->todo();

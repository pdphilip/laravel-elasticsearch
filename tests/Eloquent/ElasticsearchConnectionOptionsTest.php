<?php

declare(strict_types=1);

  use Workbench\App\Models\Post;
  use Workbench\App\Models\PostUnsafe;
  use Workbench\App\Models\Product;
  use Workbench\App\Models\ProductUnsafe;

test('whereExact when Unsafe queries is used and a keyword is not specified.', function () {
    Product::factory()->state(['name' => 'John Smith'])->create();

    $products = ProductUnsafe::whereExact('name', 'John Smith')->get();

    expect($products)->toBeEmpty();
});

test('whereExact when Unsafe queries is used and a keyword is specified.', function () {
    Product::factory()->state(['name' => 'John Smith'])->create();

    $products = ProductUnsafe::whereExact('name.keyword', 'John Smith')->get();

    expect($products->first()->name)->toEqual('John Smith');
});

test('fails whereExact on text field.', function () {
    Post::factory()->state(['content' => 'John Smith'])->create();

    $products = Post::whereExact('content', 'John Smith')->get();

    expect($products->first()->name)->toEqual('John Smith');
})->throws(PDPhilip\Elasticsearch\DSL\exceptions\ParameterException::class);

test('does not fail whereExact on text field with Unsafe queries.', function () {
    Post::factory()->state(['content' => 'John Smith'])->create();

    $posts = PostUnsafe::whereExact('content', 'John Smith')->get();

    expect($posts)->toBeEmpty();
});

<?php

declare(strict_types=1);

  use PDPhilip\Elasticsearch\Query\Builder;
  use PDPhilip\Elasticsearch\Tests\Models\Birthday;
  use PDPhilip\Elasticsearch\Tests\Models\Location;
  use PDPhilip\Elasticsearch\Tests\Models\Post;
  use PDPhilip\Elasticsearch\Tests\Models\Product;
  use PDPhilip\Elasticsearch\Tests\Models\Scoped;
  use PDPhilip\Elasticsearch\Tests\Models\User;

  beforeEach(function () {
  User::executeSchema();
  Birthday::executeSchema();
  Scoped::executeSchema();
  Product::executeSchema();
  Post::executeSchema();

    User::insert([
                   ['name' => 'John Doe', 'age' => 35, 'title' => 'admin', 'description' => 'John manages the admin team effectively.'],
                   ['name' => 'Jane Doe', 'age' => 33, 'title' => 'admin', 'description' => 'Jane oversees all administrative operations.'],
                   ['name' => 'Harry Hoe', 'age' => 13, 'title' => 'user', 'description' => 'Harry is a young user exploring the platform.'],
                   ['name' => 'Robert Roe', 'age' => 37, 'title' => 'user', 'description' => 'Robert actively participates in user discussions.'],
                   ['name' => 'Mark Moe', 'age' => 23, 'title' => 'user', 'description' => 'Mark contributes valuable feedback to the user community.'],
                   ['name' => 'Brett Boe', 'age' => 35, 'title' => 'user', 'description' => 'Brett frequently posts detailed reviews.'],
                   ['name' => 'Tommy Toe', 'age' => 33, 'title' => 'user', 'description' => 'Tommy enjoys creating content for user forums.'],
                   ['name' => 'Yvonne Yoe', 'age' => 35, 'title' => 'admin', 'description' => 'Yvonne coordinates tasks across multiple admin teams.'],
                   ['name' => 'Error', 'age' => null, 'title' => null, 'description' => null],
                 ]);

  Birthday::insert([
                     ['name' => 'Mark Moe', 'birthday' => new DateTime('2020-04-10 10:53:11')],
                     ['name' => 'Jane Doe', 'birthday' => new DateTime('2021-05-12 10:53:12')],
                     ['name' => 'Harry Hoe', 'birthday' => new DateTime('2021-05-11 10:53:13')],
                     ['name' => 'Robert Doe', 'birthday' => new DateTime('2021-05-12 10:53:14')],
                     ['name' => 'Mark Moe', 'birthday' => new DateTime('2021-05-12 10:53:15')],
                     ['name' => 'Mark Moe', 'birthday' => new DateTime('2022-05-12 10:53:16')],
                     ['name' => 'Boo'],
                   ]);

  Product::insert([
                    ['product' => 'chocolate', 'price' => [20, 5]],
                    ['product' => 'pumpkin', 'price' => 30],
                    ['product' => 'apple', 'price' => 10],
                    ['product' => 'orange juice', 'price' => [25, 7.5]],
                    ['product' => 'coffee', 'price' => 15],
                    ['product' => 'tea', 'price' => 12],
                    ['product' => 'cookies', 'price' => [18, 4.5]],
                    ['product' => 'ice cream', 'price' => 22],
                    ['product' => 'bagel', 'price' => 8],
                    ['product' => 'salad', 'price' => 14],
                    ['product' => 'sandwich', 'price' => [30, 18.5]],
                    ['product' => 'pizza', 'price' => 45],
                    ['product' => 'water', 'price' => 5],
                    ['product' => 'soda', 'price' => [8, 3]],
                    ['product' => 'error', 'price' => null],
                  ]);

    Post::insert([
                   [
                     'title' => 'Getting Started with Laravel',
                     'status' => 2,
                     'comments' => [
                       ['name' => 'John Doe', 'comment' => 'This was the perfect introduction to Laravel, thank you!', 'country' => 'USA', 'likes' => 15],
                       ['name' => 'Jane Smith', 'comment' => 'I feel much more confident starting my first Laravel project.', 'country' => 'UK', 'likes' => 8],
                       ['name' => 'Akira Tanaka', 'comment' => 'I loved the step-by-step guide!', 'country' => 'Japan', 'likes' => 12],
                     ]
                   ],
                   [
                     'title' => 'Exploring Laravel Testing',
                     'status' => 1,
                     'comments' => [
                       ['name' => 'Michael Brown', 'comment' => 'Laravel testing is super powerful!', 'country' => 'USA', 'likes' => 10],
                       ['name' => 'Emily Davis', 'comment' => 'I struggled at first but now it makes sense.', 'country' => 'Australia', 'likes' => 35],
                     ]
                   ],
                   [
                     'title' => 'Understanding Eloquent Relationships',
                     'status' => 1,
                     'comments' => [
                       ['name' => 'Carlos Ruiz', 'comment' => 'Great examples in this post!', 'country' => 'Spain', 'likes' => 18],
                       ['name' => 'Sofia Lopez', 'comment' => 'I need help with many-to-many relationships.', 'country' => 'Mexico', 'likes' => 9],
                       ['name' => 'Liam O’Connor', 'comment' => 'This cleared up a lot of confusion for me.', 'country' => 'Ireland', 'likes' => 11],
                     ]
                   ],
                   [
                     'title' => 'Building APIs with Laravel',
                     'status' => 3,
                     'comments' => [
                       ['name' => 'Sarah Lee', 'comment' => 'APIs are the future of web development!', 'country' => 'South Korea', 'likes' => 20],
                       ['name' => 'James Wilson', 'comment' => 'Any tips on debugging API responses?', 'country' => 'New Zealand', 'likes' => 6],
                       ['name' => 'Anna Muller', 'comment' => 'Laravel makes building APIs so easy.', 'country' => 'Germany', 'likes' => 14],
                     ]
                   ],
                 ]);

});

it('ES Specific Queries', function () {

  $users = User::whereTermExists('title')->get();
  expect($users)->toHaveCount(8);

  $users = User::whereTermFuzzy('title', 'admik')->get();
  expect($users)->toHaveCount(3);

  $users = User::whereMatch('description', 'exploring')->get();
  expect($users)->toHaveCount(1);

  $users = User::whereMatchPhrase('description', 'exploring the')->get();
  expect($users)->toHaveCount(1);

  $users = User::whereMatchPhrasePrefix('description', 'Robert actively')->get();
  expect($users)->toHaveCount(1);

});

it('Nested Queries', function () {

  // single where in nested
  $users = Post::whereNestedObject('comments', function (Builder $query) {
    $query->whereTerm('comments.country', 'USA');
  })->get();
  expect($users)->toHaveCount(2);

  // single where NOT nested
  $posts = Post::whereNotNestedObject('comments', function (Builder $query) {
    $query->whereTerm('comments.country', 'USA');
  })->get();
  expect($posts)->toHaveCount(2);

  // dual where in nested
  $users = Post::whereNestedObject('comments', function (Builder $query) {
    $query->whereTerm('comments.country', 'USA')->where('comments.likes',  '>=',15);
  })->get();
  expect($users)->toHaveCount(1);

  // sorting
  $users = Post::orderByNested('comments.likes', 'desc')->get();
  expect($users[0]['title'])->toBe('Exploring Laravel Testing');


  // single where in nested
  $users = Post::whereNestedObject('comments', function (Builder $query) {
    $query->whereTerm('comments.country', 'USA');
  }, options: ['inner_hits' => true])->get();

  expect($users)->toHaveCount(2)
                ->and($users[0]['comments'])->toHaveCount(1)
                ->and($users[1]['comments'])->toHaveCount(1);

});

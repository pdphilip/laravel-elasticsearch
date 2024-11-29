<?php

declare(strict_types=1);

  use PDPhilip\Elasticsearch\Tests\Models\Birthday;
  use PDPhilip\Elasticsearch\Tests\Models\Location;
  use PDPhilip\Elasticsearch\Tests\Models\Product;
  use PDPhilip\Elasticsearch\Tests\Models\Scoped;
  use PDPhilip\Elasticsearch\Tests\Models\User;

  beforeEach(function () {
  User::executeSchema();
  Birthday::executeSchema();
  Scoped::executeSchema();
  Product::executeSchema();

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

  $users = User::whereRaw('description', 'Robert actively')->get();
  expect($users)->toHaveCount(1);

});

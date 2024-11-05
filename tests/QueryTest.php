<?php

declare(strict_types=1);

use Workbench\App\Models\Birthday;
use Workbench\App\Models\Book;
use Workbench\App\Models\Item;
use Workbench\App\Models\User;

beforeEach(function () {
    User::executeSchema();
    Birthday::executeSchema();

    User::insert([
        ['name' => 'John Doe', 'age' => 35, 'title' => 'admin'],
        ['name' => 'Jane Doe', 'age' => 33, 'title' => 'admin'],
        ['name' => 'Harry Hoe', 'age' => 13, 'title' => 'user'],
        ['name' => 'Robert Roe', 'age' => 37, 'title' => 'user'],
        ['name' => 'Mark Moe', 'age' => 23, 'title' => 'user'],
        ['name' => 'Brett Boe', 'age' => 35, 'title' => 'user'],
        ['name' => 'Tommy Toe', 'age' => 33, 'title' => 'user'],
        ['name' => 'Yvonne Yoe', 'age' => 35, 'title' => 'admin'],
        ['name' => 'Error', 'age' => null, 'title' => null],
    ]);
    dd(1);

    Birthday::create(['name' => 'Mark Moe', 'birthday' => new DateTimeImmutable('2020-04-10 10:53:11')]);
    Birthday::create(['name' => 'Jane Doe', 'birthday' => new DateTimeImmutable('2021-05-12 10:53:12')]);
    Birthday::create(['name' => 'Harry Hoe', 'birthday' => new DateTimeImmutable('2021-05-11 10:53:13')]);
    Birthday::create(['name' => 'Robert Doe', 'birthday' => new DateTimeImmutable('2021-05-12 10:53:14')]);
    Birthday::create(['name' => 'Mark Moe', 'birthday' => new DateTimeImmutable('2021-05-12 10:53:15')]);
    Birthday::create(['name' => 'Mark Moe', 'birthday' => new DateTimeImmutable('2022-05-12 10:53:16')]);
    Birthday::create(['name' => 'Boo']);

});

it('tests has many', function () {
    $author = User::create(['name' => 'George R. R. Martin']);
    Book::create(['title' => 'A Game of Thrones', 'author_id' => $author->id]);
    Book::create(['title' => 'A Clash of Kings', 'author_id' => $author->id]);

    $books = $author->books;
    expect($books)->toHaveCount(2);

    $user = User::create(['name' => 'John Doe']);
    Item::create(['type' => 'knife', 'user_id' => $user->id]);
    Item::create(['type' => 'shield', 'user_id' => $user->id]);
    Item::create(['type' => 'sword', 'user_id' => $user->id]);
    Item::create(['type' => 'bag', 'user_id' => null]);

    $items = $user->items;
    expect($items)->toHaveCount(3);
});

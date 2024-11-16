<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Tests\Models\User;

it('tests save without refresh', function () {
    $user = new User;
    $user->name = 'John Doe';
    $user->title = 'admin';
    $user->age = 35;
    $user->withoutRefresh()->save();
})->throwsNoExceptions();

it('tests update without refresh', function () {
    $user = new User;
    $user->name = 'John Doe';
    $user->title = 'admin';
    $user->age = 35;
    $user->save();

    $user->age = 45;
    $user->withoutRefresh()->save();

})->throwsNoExceptions();

it('tests insert without refresh', function () {

    User::withoutRefresh()->insert([
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

})->throwsNoExceptions();

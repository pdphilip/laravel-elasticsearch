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

    Birthday::insert([
       ['name' => 'Mark Moe', 'birthday' => new DateTime('2020-04-10 10:53:11')],
       ['name' => 'Jane Doe', 'birthday' => new DateTime('2021-05-12 10:53:12')],
       ['name' => 'Harry Hoe', 'birthday' => new DateTime('2021-05-11 10:53:13')],
       ['name' => 'Robert Doe', 'birthday' => new DateTime('2021-05-12 10:53:14')],
       ['name' => 'Mark Moe', 'birthday' => new DateTime('2021-05-12 10:53:15')],
       ['name' => 'Mark Moe', 'birthday' => new DateTime('2022-05-12 10:53:16')],
       ['name' => 'Boo']
    ]);

});

  it('tests where clause', function () {
    $users = User::where('age', 35)->get();
    expect($users)->toHaveCount(3);

    $users = User::where('age', '=', 35)->get();
    expect($users)->toHaveCount(3);

    $users = User::where('age', '>=', 35)->get();
    expect($users)->toHaveCount(4);

    $users = User::where('age', '<=', 18)->get();
    expect($users)->toHaveCount(1);

    $users = User::where('age', '!=', 35)->get();
    expect($users)->toHaveCount(6);

    $users = User::where('age', '<>', 35)->get();
    expect($users)->toHaveCount(6);
  });

  it('tests and where clause', function () {
    $users = User::where('age', 35)->where('title', 'admin')->get();
    expect($users)->toHaveCount(2);

    $users = User::where('age', '>=', 35)->where('title', 'user')->get();
    expect($users)->toHaveCount(2);
  });

  it('tests regexp clause', function () {
    User::create(['name' => 'Simple', 'company' => 'acme']);
    User::create(['name' => 'With slash', 'company' => 'oth/er']);

    $users = User::whereRegex('company', '/^acme$/')->get();
    expect($users)->toHaveCount(1);

    $users = User::whereRegex('company', '/^ACME$/i')->get();
    expect($users)->toHaveCount(1);

    $users = User::whereRegex('company', '/^oth\/er$/')->get();
    expect($users)->toHaveCount(1);
  })->todo();

  it('tests like clause', function () {
    $users = User::where('name', 'like', '%doe')->get();
    expect($users)->toHaveCount(2);

    $users = User::where('name', 'like', '%y%')->get();
    expect($users)->toHaveCount(3);

    $users = User::where('name', 'LIKE', '%y%')->get();
    expect($users)->toHaveCount(3);

    $users = User::where('name', 'like', 't%')->get();
    expect($users)->toHaveCount(1);

    $users = User::where('name', 'like', 'j___ doe')->get();
    expect($users)->toHaveCount(2);

    $users = User::where('name', 'like', '_oh_ _o_')->get();
    expect($users)->toHaveCount(1);
  });

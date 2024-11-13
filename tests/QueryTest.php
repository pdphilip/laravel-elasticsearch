<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Tests\Models\Birthday;
use PDPhilip\Elasticsearch\Tests\Models\Scoped;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    User::executeSchema();
    Birthday::executeSchema();
    Scoped::executeSchema();

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
        ['name' => 'Boo'],
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

    $users = User::whereRegex('company', 'acme')->get();
    expect($users)->toHaveCount(1);

    $users = User::whereRegex('company', 'ACME')->withParameters(['case_insensitive' => true])->get();
    expect($users)->toHaveCount(1);

    $users = User::whereRegex('company', 'oth*')->get();
    expect($users)->toHaveCount(1);
});

it('tests like clause', function () {
    $users = User::where('name', 'like', '%doe')->withParameters(['case_insensitive' => true])->get();
    expect($users)->toHaveCount(2);

    $users = User::where('name', 'like', '%y%')->withParameters(['case_insensitive' => true])->get();
    expect($users)->toHaveCount(3);

    $users = User::where('name', 'like', 't%')->withParameters(['case_insensitive' => true])->get();
    expect($users)->toHaveCount(1);

});

it('tests not like clause', function () {
    $users = User::where('name', 'not like', '%doe')->withParameters(['case_insensitive' => true])->get();
    expect($users)->toHaveCount(7);

    $users = User::where('name', 'not like', '%y%')->withParameters(['case_insensitive' => true])->get();
    expect($users)->toHaveCount(6);

    $users = User::where('name', 'not like', 't%')->withParameters(['case_insensitive' => true])->get();
    expect($users)->toHaveCount(8);
});

it('selects specific columns for users', function () {
    $user = User::where('name', 'John Doe')->select('name')->first();

    expect($user->name)->toBe('John Doe')
        ->and($user->age)->toBeNull()
        ->and($user->title)->toBeNull();

    $user = User::where('name', 'John Doe')->select('name', 'title')->first();

    expect($user->name)->toBe('John Doe')
        ->and($user->title)->toBe('admin')
        ->and($user->age)->toBeNull();

    $user = User::where('name', 'John Doe')->select(['name', 'title'])->get()->first();

    expect($user->name)->toBe('John Doe')
        ->and($user->title)->toBe('admin')
        ->and($user->age)->toBeNull();

    $user = User::where('name', 'John Doe')->get(['name'])->first();

    expect($user->name)->toBe('John Doe')
        ->and($user->age)->toBeNull();
});

it('filters users with whereNot', function () {
    expect(User::whereNot('title', 'admin')->get())->toHaveCount(6);
    expect(User::whereNot(fn ($builder) => $builder->where('title', 'admin'))->get())->toHaveCount(6);
    expect(User::whereNot('title', '!=', 'admin')->get())->toHaveCount(3);
    expect(User::whereNot(fn ($builder) => $builder->whereNot('title', 'admin'))->get())->toHaveCount(3);
    expect(User::whereNot('title', '=', 'admin')->get())->toHaveCount(6);
    expect(User::whereNot('title', ['$in' => ['admin']])->get())->toHaveCount(6);
    expect(User::whereNot('title', new Regex('^admin$'))->get())->toHaveCount(6);
    expect(User::whereNot('title', null)->get())->toHaveCount(8);
    expect(User::whereNot(fn ($builder) => $builder->where('title', 'admin')->orWhere('age', 35))->get())->toHaveCount(5);
})->todo();

it('filters users with orWhere', function () {
    expect(User::where('age', 13)->orWhere('title', 'admin')->get())->toHaveCount(4)
        ->and(User::where('age', 13)->orWhere('age', 23)->get())->toHaveCount(2);
});

it('filters users within range with whereBetween', function () {
    expect(
        User::whereBetween('age', [
            0,
            25,
        ])->get()
    )->toHaveCount(2)
        ->and(
            User::whereBetween('age', [
                13,
                23,
            ])->get()
        )->toHaveCount(2)
        ->and(
            User::whereBetween('age', [
                0,
                25,
            ], 'and', true)->get()
        )->toHaveCount(6);
});

it('filters users with whereIn and whereNotIn', function () {
    expect(User::whereIn('age', [13, 23])->get())->toHaveCount(2);
    expect(User::whereIn('age', [33, 35, 13])->get())->toHaveCount(6);
    expect(User::whereNotIn('age', [33, 35])->get())->toHaveCount(4);
    expect(User::whereNotNull('age')->whereNotIn('age', [33, 35])->get())->toHaveCount(3);
})->todo('this needs to be text base');

it('filters users by null values with whereNull', function () {
    expect(User::whereNull('age')->get())->toHaveCount(1);
});

it('filters users by non-null values with whereNotNull', function () {
    expect(User::whereNotNull('age')->get())->toHaveCount(8);
});

it('filters birthdays by specific dates with whereDate', function () {
    expect(Birthday::whereDate('birthday', '2021-05-12')->get())->toHaveCount(3)
        ->and(Birthday::whereDate('birthday', '2021-05-11')->get())->toHaveCount(1)
        ->and(Birthday::whereDate('birthday', '>', '2021-05-11')->get())->toHaveCount(4)
        ->and(Birthday::whereDate('birthday', '>=', '2021-05-11')->get())->toHaveCount(5)
        ->and(Birthday::whereDate('birthday', '<', '2021-05-11')->get())->toHaveCount(1)
        ->and(Birthday::whereDate('birthday', '<=', '2021-05-11')->get())->toHaveCount(2)
        ->and(Birthday::whereDate('birthday', '<>', '2021-05-11')->get())->toHaveCount(6);
});

it('filters birthdays by day with whereDay', function () {
    expect(Birthday::whereDay('birthday', '12')->get())->toHaveCount(4)
        ->and(Birthday::whereDay('birthday', '11')->get())->toHaveCount(1);
});

it('filters birthdays by month with whereMonth', function () {
    expect(Birthday::whereMonth('birthday', '04')->get())->toHaveCount(1)
        ->and(Birthday::whereMonth('birthday', 5)->get())->toHaveCount(5)
        ->and(Birthday::whereMonth('birthday', '>=', 5)->get())->toHaveCount(5)
        ->and(Birthday::whereMonth('birthday', '<', 10)->get())->toHaveCount(6)
        ->and(Birthday::whereMonth('birthday', '<>', 5)->get())->toHaveCount(2);
});

it('filters birthdays by year with whereYear', function () {
    expect(Birthday::whereYear('birthday', '2021')->get())->toHaveCount(4)
        ->and(Birthday::whereYear('birthday', '2022')->get())->toHaveCount(1)
        ->and(Birthday::whereYear('birthday', '<', '2021')->get())->toHaveCount(1)
        ->and(Birthday::whereYear('birthday', '<>', '2021')->get())->toHaveCount(2);
});

it('filters birthdays by specific time with whereTime', function () {
    expect(Birthday::whereTime('birthday', '10:53:11')->get())->toHaveCount(1);
    expect(Birthday::whereTime('birthday', '10:53')->get())->toHaveCount(6);
    expect(Birthday::whereTime('birthday', '10')->get())->toHaveCount(6);
    expect(Birthday::whereTime('birthday', '>=', '10:53:14')->get())->toHaveCount(3);
    expect(Birthday::whereTime('birthday', '!=', '10:53:14')->get())->toHaveCount(6);
    expect(Birthday::whereTime('birthday', '<', '10:53:12')->get())->toHaveCount(2);
})->todo('Need to complete this');

it('orders users by age and natural column', function () {
    $user = User::whereNotNull('age')->orderBy('age', 'asc')->first();
    expect($user->age)->toBe(13);

    $user = User::whereNotNull('age')->orderBy('age', 'ASC')->first();
    expect($user->age)->toBe(13);

    $user = User::whereNotNull('age')->orderBy('age', 'desc')->first();
    expect($user->age)->toBe(37);

    $user = User::whereNotNull('age')->orderBy('natural', 'asc')->first();
    expect($user->age)->toBe(35);

    $user = User::whereNotNull('age')->orderBy('natural', 'ASC')->first();
    expect($user->age)->toBe(35);

    $user = User::whereNotNull('age')->orderBy('natural', 'desc')->first();
    expect($user->age)->toBe(35);
});

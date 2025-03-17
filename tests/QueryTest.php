<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Tests\Models\Birthday;
use PDPhilip\Elasticsearch\Tests\Models\Product;
use PDPhilip\Elasticsearch\Tests\Models\Scoped;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    User::executeSchema();
    Birthday::executeSchema();
    Scoped::executeSchema();
    Product::executeSchema();

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

    $users = User::whereRegex('company', 'ACME', options: ['case_insensitive' => true])->get();
    expect($users)->toHaveCount(1);

    $users = User::whereRegex('company', 'oth...*')->get();
    expect($users)->toHaveCount(1);
});

it('tests like clause', function () {
    $users = User::where('name', 'like', '%doe', options: ['case_insensitive' => true])->get();
    expect($users)->toHaveCount(2);

    $users = User::where('name', 'like', '%y%', options: ['case_insensitive' => true])->get();
    expect($users)->toHaveCount(3);

    $users = User::where('name', 'like', 't%', options: ['case_insensitive' => true])->get();
    expect($users)->toHaveCount(1);

});

it('tests not like clause', function () {
    $users = User::where('name', 'not like', '%doe', options: ['case_insensitive' => true])->get();
    expect($users)->toHaveCount(7);

    $users = User::where('name', 'not like', '%y%', options: ['case_insensitive' => true])->get();
    expect($users)->toHaveCount(6);

    $users = User::where('name', 'not like', 't%', options: ['case_insensitive' => true])->get();
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
    expect(User::whereNot('title', 'admin')->get())->toHaveCount(6)
        ->and(User::whereNot(fn ($builder) => $builder->where('title', 'admin'))->get())->toHaveCount(6)
        ->and(User::whereNot('title', '!=', 'admin')->get())->toHaveCount(3)
        ->and(User::whereNot(fn ($builder) => $builder->where('title', '!=', 'admin'))->get())->toHaveCount(3)
        ->and(User::whereNot('title', '=', 'admin')->get())->toHaveCount(6)
        ->and(User::whereNot('title', null)->get())->toHaveCount(8)
        ->and(User::whereNot(fn ($builder) => $builder->where('title', 'admin')->orWhere('age', 35))->get())->toHaveCount(5);
});

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
            User::whereNotBetween('age', [
                0,
                25,
            ])->get()
        )->toHaveCount(7, 'this should be 7 (not 6) because null is not between 0 and 25')
        ->and(
            User::whereBetween('age', [
                13,
                23,
            ])->orWhereBetween('age', [
                33,
                36,
            ])->get()
        )->toHaveCount(7);
});

it('filters users with whereIn and whereNotIn', function () {
    expect(
        User::whereIn('age', [
            13,
            23,
        ])->get()
    )->toHaveCount(2)
        ->and(
            User::whereIn('age', [
                33,
                35,
                13,
            ])->get()
        )->toHaveCount(6)
        ->and(
            User::whereNotIn('age', [
                33,
                35,
            ])->get()
        )->toHaveCount(4)
        ->and(
            User::whereNotNull('age')->whereNotIn('age', [
                33,
                35,
            ])->get()
        )->toHaveCount(3);
});

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
        ->and(Birthday::whereMonth('birthday', '<>', 5)->get())->toHaveCount(1);
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

it('orders users by age', function () {
    $user = User::whereNotNull('age')->orderBy('age', 'asc')->first();
    expect($user->age)->toBe(13);

    $user = User::whereNotNull('age')->orderBy('age', 'ASC')->first();
    expect($user->age)->toBe(13);

    $user = User::whereNotNull('age')->orderBy('age', 'desc')->first();
    expect($user->age)->toBe(37);

    $user = User::whereNotNull('age')->orderByDesc('age')->first();
    expect($user->age)->toBe(37);
});

it('counts users with specific age criteria', function () {
    expect(User::where('age', '<>', 35)->count())->toBe(6)
        ->and(User::select('id', 'age', 'title')->where('age', '<>', 35)->count())->toBe(6);
});

it('checks existence of users based on age conditions', function () {
    expect(User::where('age', '>', 37)->exists())->toBeFalse()
        ->and(User::where('age', '<', 37)->exists())->toBeTrue();
});

it('filters users using subquery conditions', function () {
    expect(User::where('title', 'admin')->orWhere(fn ($query) => $query->where('name', 'Tommy Toe')->orWhere('name', 'Error'))->get())->toHaveCount(5)
        ->and(User::where('title', 'user')->where(fn ($query) => $query->where('age', 35)->orWhere('name', 'like', '%Harry%'))->get())->toHaveCount(2)
        ->and(User::where('age', 35)->orWhere(fn ($query) => $query->where('title', 'admin')->orWhere('name', 'Error'))->get())->toHaveCount(5)
        ->and(User::whereNull('deleted_at')->where('title', 'admin')->where(fn ($query) => $query->where('age', '>', 15)->orWhere('name', 'Harry Hoe'))->get())->toHaveCount(3)
        ->and(User::whereNull('deleted_at')->where(fn ($query) => $query->where('name', 'Harry Hoe')->orWhere(fn ($query) => $query->where('age', '>', 15)->where('title', '<>', 'admin')))->get())->toHaveCount(5);
});

it('filters users using raw conditions', function () {
    $where = [
        'range' => [
            'age' => [
                'gt' => 30,
                'lt' => 40,
            ],
        ],
    ];
    expect(User::whereRaw($where)->get())->toHaveCount(6);

    $where1 = [
        'range' => [
            'age' => [
                'gte' => 30,
                'lte' => 35,
            ],
        ],
    ];

    $where2 = [
        'range' => [
            'age' => [
                'gte' => 35,
                'lte' => 40,
            ],
        ],
    ];

    expect(User::whereRaw($where1)->orWhereRaw($where2)->get())->toHaveCount(6);
});

it('filters users with multiple OR conditions', function () {
    $users = User::where(fn ($query) => $query->where('age', 35)->orWhere('age', 33))
        ->where(fn ($query) => $query->where('name', 'John Doe')->orWhere('name', 'Jane Doe'))
        ->get();
    expect($users)->toHaveCount(2);

    $users = User::where(fn ($query) => $query->orWhere('age', 35)->orWhere('age', 33))
        ->where(fn ($query) => $query->orWhere('name', 'John Doe')->orWhere('name', 'Jane Doe'))
        ->get();
    expect($users)->toHaveCount(2);
});

it('paginates results', function () {
    $results = User::paginate(2);
    expect($results->count())->toBe(2)
        ->and($results->first()->title)->not()->toBeNull()
        ->and($results->total())->toBe(9);

    $results = User::paginate(2, ['name', 'age']);
    expect($results->count())->toBe(2)
        ->and($results->first()->title)->toBeNull()
        ->and($results->total())->toBe(9)
        ->and($results->currentPage())->toBe(1);
});

it('uses cursor pagination', function () {
    $results = User::cursorPaginate(2);
    expect($results->count())->toBe(2)
        ->and($results->first()->title)->not()->toBeNull()
        ->and($results->nextCursor())->not()->toBeNull()
        ->and($results->onFirstPage())->toBeTrue();

    $results = User::cursorPaginate(2, ['name', 'age']);
    expect($results->count())->toBe(2)
        ->and($results->first()->title)->toBeNull();

    $results = User::orderBy('age', 'desc')->cursorPaginate(2, ['name', 'age']);
    expect($results->count())->toBe(2)
        ->and($results->first()->age)->toBe(37)
        ->and($results->first()->title)->toBeNull();
});

it('aggregates results by age', function () {

    expect(User::max('age'))->toBe(37.0)
        ->and(User::min('age'))->toBe(13.0)
        ->and(User::avg('age'))->toBe(30.5)
        ->and(User::sum('age'))->toBe(244.0);

});

it('tests groupby', function () {

    $users = User::groupBy('title')->where('age', 23)->get();
    expect($users)->toHaveCount(1);

    $users = User::groupBy('title')->get();
    expect($users)->toHaveCount(2)
        ->and($users[0]['title'])->toBe('admin')
        ->and($users[0]->getMeta()->getDocCount())->toBe(3)
        ->and($users[1]['title'])->toBe('user')
        ->and($users[1]->getMeta()->getDocCount())->toBe(5);

    $users = User::groupBy('age')->get();
    expect($users)->toHaveCount(5);

    $users = User::groupBy('age')->take(2)->get();
    expect($users)->toHaveCount(2);

    $users = User::groupBy('title', 'age')->get();
    expect($users)->toHaveCount(7)
        ->and($users[0]->age)->toBe(33)
        ->and($users[0]->title)->toBe('admin')
        ->and($users[1]->age)->toBe(35)
        ->and($users[1]->title)->toBe('admin')
        ->and($users[2]->age)->toBe(13)
        ->and($users[2]->title)->toBe('user');
});

it('updates records', function () {
    expect(User::where(['name' => 'John Doe'])->update(['name' => 'Jim Morrison']))->toBe(1)
        ->and(User::where(['name' => 'Jim Morrison'])->count())->toBe(1);
});

it('fetches unsorted results', function () {
    $unsortedResults = User::get();
    $unsortedSubset = $unsortedResults->where('age', 35)->values();

    expect($unsortedSubset[0]->name)->toBe('John Doe')
        ->and($unsortedSubset[1]->name)->toBe('Brett Boe')
        ->and($unsortedSubset[2]->name)->toBe('Yvonne Yoe');
});

it('applies multiple sort orders', function () {
    $results = User::orderBy('age')->orderBy('name')->get();
    $subset = $results->where('age', 35)->values();

    expect($subset[0]->name)->toBe('Brett Boe')
        ->and($subset[1]->name)->toBe('John Doe')
        ->and($subset[2]->name)->toBe('Yvonne Yoe');
});

it('sorts by age and name in descending order', function () {
    $results = User::orderBy('age')->orderBy('name', 'desc')->get();
    $subset = $results->where('age', 35)->values();

    expect($subset[0]->name)->toBe('Yvonne Yoe')
        ->and($subset[1]->name)->toBe('John Doe')
        ->and($subset[2]->name)->toBe('Brett Boe');
});

it('can apply ES specific sorts', function () {
    $results = Product::orderBy('price', 'desc', ['mode' => 'sum'])->first();
    expect($results->product)->toBe('sandwich');

    $results = Product::orderBy('price', 'desc', ['mode' => 'avg'])->first();
    expect($results->product)->toBe('pizza');

    $results = Product::orderBy('price', 'desc', ['mode' => 'median'])->first();
    expect($results->product)->toBe('pizza');

    $results = Product::orderBy('price', 'desc', ['mode' => 'sum', 'missing' => '_first'])->first();
    expect($results->product)->toBe('error');

    $results = Product::orderByDesc('price', ['mode' => 'sum', 'missing' => '_first'])->first();
    expect($results->product)->toBe('error');
});

it('deletes users with specific conditions', function () {
    expect(User::where('title', 'admin')->count())->toBe(3);
    User::where('title', 'admin')->delete();
    expect(User::where('title', 'admin')->count())->toBe(0)
        ->and(User::count())->toBe(6);

    User::limit(1000)->delete();
    expect(User::count())->toBe(0);
});

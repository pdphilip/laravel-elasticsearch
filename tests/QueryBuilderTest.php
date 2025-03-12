<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use PDPhilip\Elasticsearch\Tests\Models\Item;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    User::executeSchema();
    Item::executeSchema();

    DB::table('users')->truncate();
    DB::table('items')->truncate();

});

it('tests delete with id', function () {
    $user = DB::table('users')->insertGetId([
        ['name' => 'Jane Doe', 'age' => 20],
    ]);

    $userId = (string) $user;

    DB::table('items')->insert([
        ['name' => 'one thing', 'user_id' => $userId],
        ['name' => 'last thing', 'user_id' => $userId],
        ['name' => 'another thing', 'user_id' => $userId],
        ['name' => 'one more thing', 'user_id' => $userId],
    ]);

    $product = DB::table('items')->first();

    $pid = (string) ($product['_id']);

    DB::table('items')->where('user_id', $userId)->delete($pid);

    expect(DB::table('items')->count())->toBe(3);

    $product = DB::table('items')->first();

    $pid = $product['_id'];

    DB::table('items')->where('user_id', $userId)->delete($pid);

    DB::table('items')->where('user_id', $userId)->delete(md5('random-id'));

    expect(DB::table('items')->count())->toBe(2);
});

it('tests collection', function () {
    expect(DB::table('users'))->toBeInstanceOf(\PDPhilip\Elasticsearch\Query\Builder::class);
});

it('tests get', function () {
    $users = DB::table('users')->get();
    expect($users)->toHaveCount(0);

    DB::table('users')->insert(['name' => 'John Doe']);

    $users = DB::table('users')->get();
    expect($users)->toHaveCount(1);
});

it('tests no document', function () {
    $items = DB::table('items')->where('name', 'nothing')->get()->toArray();
    expect($items)->toBe([]);

    $item = DB::table('items')->where('name', 'nothing')->first();
    expect($item)->toBeNull();

    $item = DB::table('items')->where('id', '51c33d8981fec6813e00000a')->first();
    expect($item)->toBeNull();
});

it('tests insert', function () {
    DB::table('users')->insert([
        'tags' => ['tag1', 'tag2'],
        'name' => 'John Doe',
    ]);

    $users = DB::table('users')->get();
    expect($users)->toHaveCount(1);

    $user = $users[0];
    expect($user['name'])->toBe('John Doe')
        ->and($user['tags'])->toBeArray();
});

it('tests batch insert', function () {
    DB::table('users')->insert([
        [
            'tags' => ['tag1', 'tag2'],
            'name' => 'Jane Doe',
        ],
        [
            'tags' => ['tag3'],
            'name' => 'John Doe',
        ],
    ]);

    $users = DB::table('users')->get();
    expect($users)->toHaveCount(2)
        ->and($users[0]['tags'])->toBeArray();
});

it('tests find', function () {
    $id = DB::table('users')->insertGetId(['name' => 'John Doe']);

    $user = DB::table('users')->find($id);
    expect($user['name'])->toBe('John Doe');
});

it('tests find null', function () {
    $user = DB::table('users')->find(null);
    expect($user)->toBeNull();
});

it('tests count', function () {
    DB::table('users')->insert([
        ['name' => 'Jane Doe'],
        ['name' => 'John Doe'],
    ]);

    expect(DB::table('users')->count())->toBe(2);
});

it('tests update', function () {
    DB::table('users')->insert([
        ['name' => 'Jane Doe', 'age' => 20],
        ['name' => 'John Doe', 'age' => 21],
    ]);

    DB::table('users')->where('name', 'John Doe')->update(['age' => 100]);

    $john = DB::table('users')->where('name', 'John Doe')->first();
    $jane = DB::table('users')->where('name', 'Jane Doe')->first();

    expect($john['age'])->toBe(100)
        ->and($jane['age'])->toBe(20);
});

it('tests delete', function () {
    DB::table('users')->insert([
        ['name' => 'Jane Doe', 'age' => 20],
        ['name' => 'John Doe', 'age' => 25],
    ]);

    DB::table('users')->where('age', '<', 10)->delete();
    expect(DB::table('users')->count())->toBe(2);

    DB::table('users')->where('age', '<', 25)->delete();
    expect(DB::table('users')->count())->toBe(1);
});

it('tests truncate', function () {
    DB::table('users')->insert(['name' => 'John Doe']);
    DB::table('users')->insert(['name' => 'John Doe']);

    expect(DB::table('users')->count())->toBe(2);

    DB::table('users')->truncate();

    expect(DB::table('users')->count())->toBe(0);
});

it('tests sub key', function () {
    DB::table('users')->insert([
        [
            'name' => 'John Doe',
            'address' => ['country' => 'Belgium', 'city' => 'Ghent'],
        ],
        [
            'name' => 'Jane Doe',
            'address' => ['country' => 'France', 'city' => 'Paris'],
        ],
    ]);

    $users = DB::table('users')->where('address.country', 'Belgium')->get();

    expect($users)->toHaveCount(1)
        ->and($users[0]['name'])->toBe('John Doe');
});

it('tests in array', function () {
    DB::table('items')->insert([
        ['tags' => ['tag1', 'tag2', 'tag3', 'tag4']],
        ['tags' => ['tag2']],
    ]);

    $items = DB::table('items')->where('tags', 'tag2')->get();
    expect($items)->toHaveCount(2);

    $items = DB::table('items')->where('tags', 'tag1')->get();
    expect($items)->toHaveCount(1);
});

it('tests distinct', function () {
    DB::table('items')->insert([
        ['name' => 'knife', 'type' => 'sharp'],
        ['name' => 'fork', 'type' => 'sharp'],
        ['name' => 'spoon', 'type' => 'round'],
        ['name' => 'spoon', 'type' => 'round'],
    ]);

    $items = DB::table('items')->select('name')->distinct()->pluck('name')->sort()->values()->toArray();
    expect($items)->toHaveCount(3)->toEqual(['fork', 'knife', 'spoon']);

    $types = DB::table('items')->select('type')->distinct()->pluck('type')->sort()->values()->toArray();
    expect($types)->toHaveCount(2)->toEqual(['round', 'sharp']);

    $types = DB::table('items')->distinct()->pluck('type')->sort()->values()->toArray();
    expect($types)->toHaveCount(4)->toEqual(['round', 'round', 'sharp', 'sharp']);
});

it('tests custom ID', function () {
    $tags = [['id' => 'sharp', 'name' => 'Sharp']];
    DB::table('items')->insert([
        ['id' => 'knife', 'type' => 'sharp', 'amount' => 34, 'tags' => $tags],
        ['id' => 'fork', 'type' => 'sharp', 'amount' => 20, 'tags' => $tags],
        ['id' => 'spoon', 'type' => 'round', 'amount' => 3],
    ]);

    $item = DB::table('items')->find('knife');
    expect($item['_id'])->toBe('knife')
        ->and($item)->not->toHaveProperty('id')
        ->and($item['tags'][0]['id'])->toBe('sharp')
        ->and($item['tags'][0])->not->toHaveKey('_id');

    $item = DB::table('items')->where('_id', 'fork')->first();
    expect($item['_id'])->toBe('fork');

    $items = DB::table('items')->whereIn('tags.id', ['sharp'])->get();
    expect($items)->toHaveCount(2);
});

it('tests take', function () {
    DB::table('items')->insert([
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 14],
    ]);

    $items = DB::table('items')->orderBy('name')->take(2)->get();
    expect($items)->toHaveCount(2)
        ->and($items[0]['name'])->toBe('fork');
});

it('tests skip and take', function () {
    DB::table('items')->insert([
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 14],
    ]);

    $items = DB::table('items')->orderBy('name')->skip(2)->get();
    expect($items)->toHaveCount(2)
        ->and($items[0]['name'])->toBe('spoon');

    $items = DB::table('items')->orderBy('name')->skip(1)->take(1)->get();
    expect($items)->toHaveCount(1)
        ->and($items[0]['name'])->toBe('knife');
});

it('tests pluck', function () {
    DB::table('users')->insert([
        ['name' => 'Jane Doe', 'age' => 20],
        ['name' => 'John Doe', 'age' => 25],
    ]);

    $age = DB::table('users')->where('name', 'John Doe')->pluck('age')->toArray();
    expect($age)->toEqual([25]);
});

it('tests list', function () {
    DB::table('items')->insert([
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34, 'cost' => 3.40],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20, 'cost' => 2.00],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3, 'cost' => 3.0],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 14, 'cost' => 1.40],
    ]);

    $list = DB::table('items')->pluck('name')->sort()->values()->toArray();
    expect($list)->toHaveCount(4)->toEqual(['fork', 'knife', 'spoon', 'spoon']);
});

it('tests aggregate', function () {
    DB::table('items')->insert([
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34, 'cost' => 3.40],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20, 'cost' => 2.00],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3, 'cost' => 3.0],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 14, 'cost' => 1.40],
    ]);

    expect(DB::table('items')->sum('amount'))->toBe(71.0)
        ->and(DB::table('items')->count('amount'))->toBe(4)
        ->and(DB::table('items')->min('amount'))->toBe(3.0)
        ->and(DB::table('items')->max('amount'))->toBe(34.0)
        ->and(DB::table('items')->avg('amount'))->toBe(17.75);
});

it('tests subdocument aggregate', function () {
    DB::table('items')->insert([
        ['name' => 'knife', 'amount' => ['hidden' => 10, 'found' => 3]],
        ['name' => 'fork', 'amount' => ['hidden' => 35, 'found' => 12]],
        ['name' => 'spoon', 'amount' => ['hidden' => 14, 'found' => 21]],
        ['name' => 'spoon', 'amount' => ['hidden' => 6, 'found' => 4]],
    ]);

    expect(DB::table('items')->sum('amount.hidden'))->toBe(65.0)
        ->and(DB::table('items')->count('amount.hidden'))->toBe(4)
        ->and(DB::table('items')->min('amount.hidden'))->toBe(6.0)
        ->and(DB::table('items')->max('amount.hidden'))->toBe(35.0)
        ->and(DB::table('items')->avg('amount.hidden'))->toBe(16.25);
});

it('updates subdocument fields', function () {
    $id = DB::table('users')->insertGetId(['name' => 'John Doe', 'address' => ['country' => 'Belgium']]);

    DB::table('users')->where('id', $id)->update(['address.country' => 'England']);

    $check = DB::table('users')->find($id);
    expect($check['address']['country'])->toBe('England');
});

it('handles dates correctly', function () {
    DB::table('users')->insert([
        ['name' => 'John Doe', 'birthday' => Date::parse('1980-01-01 00:00:00')],
        ['name' => 'Robert Roe', 'birthday' => Date::parse('1982-01-01 00:00:00')],
        ['name' => 'Mark Moe', 'birthday' => Date::parse('1983-01-01 00:00:00.1')],
        ['name' => 'Frank White', 'birthday' => Date::parse('1975-01-01 12:12:12.1')],
    ]);

    $user = DB::table('users')
        ->where('birthday', Date::parse('1980-01-01 00:00:00'))
        ->first();
    expect($user['name'])->toBe('John Doe');

    $user = DB::table('users')
        ->where('birthday', Date::parse('1975-01-01 12:12:12.1'))
        ->first();
    expect($user['name'])->toBe('Frank White')
        ->and($user['birthday'])->toBe('1975-01-01T12:12:12+00:00');

    $user = DB::table('users')->where('birthday', '=', new DateTime('1980-01-01 00:00:00'))->first();
    expect($user['name'])->toBe('John Doe')
        ->and($user['birthday'])->toBe('1980-01-01T00:00:00+00:00');

    $user = DB::table('users')->whereDate('birthday', '=', new DateTime('1980-01-01 00:00:00'))->first();
    expect($user['name'])->toBe('John Doe')
        ->and($user['birthday'])->toBe('1980-01-01T00:00:00+00:00');

    $start = new DateTime('1950-01-01 00:00:00');
    $stop = new DateTime('1981-01-01 00:00:00');

    $users = DB::table('users')->whereBetween('birthday', [$start, $stop])->get();
    expect($users)->toHaveCount(2);

});

it('uses pagination', function () {
    DB::table('users')->insert([
        ['name' => 'John Doe', 'age' => 30],
        ['name' => 'Jane Doe'],
        ['name' => 'Robert Roe', 'age' => 29],
        ['name' => 'Lisa Roe', 'age' => 5],
    ]);

    $results = DB::table('users')->whereStartsWith('name', 'John')->limit(2)->getCountForPagination();
    expect($results)->toBe(1);

});

it('uses various query operators', function () {
    DB::table('users')->insert([
        ['name' => 'John Doe', 'age' => 30],
        ['name' => 'Jane Doe'],
        ['name' => 'Robert Roe', 'age' => 29],
        ['name' => 'Lisa Roe', 'age' => 5],
    ]);

    $results = DB::table('users')->where('age', 'exists', true)->get();
    expect($results)->toHaveCount(3)
        ->and($results->pluck('name'))->toContain('John Doe', 'Robert Roe');

    $results = DB::table('users')->whereWithOptions('Basic', 'age', '=', 5, [])->get();
    expect($results)->toHaveCount(1)
        ->and($results->pluck('name'))->toContain('Lisa Roe');

    $results = DB::table('users')
        ->where('age', '>', 4)
        ->filterWhere('name', '=', 'John Doe')
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->pluck('name'))->toContain('John Doe');

    $results = DB::table('users')->whereStartsWith('name', 'John')->get();
    expect($results)->toHaveCount(1)
        ->and($results->pluck('name'))->toContain('John Doe');

    $results = DB::table('users')->whereNot(function ($query) {
        return $query->where('name', 'John Doe');
    })->get();

    expect($results)->toHaveCount(3)
        ->and($results->pluck('name'))->not->toContain('John Doe');

    $script = "doc['age'].size() > 0 && doc['age'].value >= params.value";
    $options['params'] = ['value' => 29];

    $results = DB::table('users')->whereScript($script, options: $options)->get();
    expect($results)->toHaveCount(2)
        ->and($results->pluck('name'))->toContain('John Doe', 'Robert Roe');

});

it('uses various date query operators', function () {

    DB::table('users')->insert([
        ['name' => 'Mon-Jan-2024', 'birthday' => Date::parse('2024-01-01 00:00:00'), 'month' => 'January', 'year' => 2024, 'day_of_week' => 'Monday', 'day' => 1],
        ['name' => 'Tues-Jan-2024', 'birthday' => Date::parse('2024-01-02 00:00:00'), 'month' => 'January', 'year' => 2024, 'day_of_week' => 'Tuesday', 'day' => 2],
        ['name' => 'Wed-Jan-2024', 'birthday' => Date::parse('2024-01-03 00:00:00'), 'month' => 'January', 'year' => 2024, 'day_of_week' => 'Wednesday', 'day' => 3],
        ['name' => 'Thur-Jan-2024', 'birthday' => Date::parse('2024-01-04 00:00:00'), 'month' => 'January', 'year' => 2024, 'day_of_week' => 'Thursday', 'day' => 4],
        ['name' => 'Fri-Jan-2024', 'birthday' => Date::parse('2024-01-05 00:00:00'), 'month' => 'January', 'year' => 2024, 'day_of_week' => 'Friday', 'day' => 5],
        ['name' => 'Sat-Jan-2024', 'birthday' => Date::parse('2024-01-06 00:00:00'), 'month' => 'January', 'year' => 2024, 'day_of_week' => 'Saturday', 'day' => 6],
        ['name' => 'Sun-Jan-2024', 'birthday' => Date::parse('2024-01-07 00:00:00'), 'month' => 'January', 'year' => 2024, 'day_of_week' => 'Sunday', 'day' => 7],

        ['name' => 'Sun-Jan-2023', 'birthday' => Date::parse('2023-01-15 00:00:00'), 'month' => 'January', 'year' => 2023, 'day_of_week' => 'Sunday', 'day' => 15],
        ['name' => 'Mon-Feb-2023', 'birthday' => Date::parse('2023-02-20 00:00:00'), 'month' => 'February', 'year' => 2023, 'day_of_week' => 'Monday', 'day' => 20],
        ['name' => 'Fri-Mar-2023', 'birthday' => Date::parse('2023-03-10 00:00:00'), 'month' => 'March', 'year' => 2023, 'day_of_week' => 'Friday', 'day' => 10],
        ['name' => 'Wed-Apr-2023', 'birthday' => Date::parse('2023-04-12 00:00:00'), 'month' => 'April', 'year' => 2023, 'day_of_week' => 'Wednesday', 'day' => 12],
        ['name' => 'Thur-May-2023', 'birthday' => Date::parse('2023-05-18 00:00:00'), 'month' => 'May', 'year' => 2023, 'day_of_week' => 'Thursday', 'day' => 18],
        ['name' => 'Sun-Jun-2023', 'birthday' => Date::parse('2023-06-25 00:00:00'), 'month' => 'June', 'year' => 2023, 'day_of_week' => 'Sunday', 'day' => 25],
        ['name' => 'Sun-Jul-2023', 'birthday' => Date::parse('2023-07-30 00:00:00'), 'month' => 'July', 'year' => 2023, 'day_of_week' => 'Sunday', 'day' => 30],

        ['name' => 'Sun-Jan-2025', 'birthday' => Date::parse('2025-01-05 00:00:00'), 'month' => 'January', 'year' => 2025, 'day_of_week' => 'Sunday', 'day' => 5],
        ['name' => 'Fri-Feb-2025', 'birthday' => Date::parse('2025-02-14 00:00:00'), 'month' => 'February', 'year' => 2025, 'day_of_week' => 'Friday', 'day' => 14],
        ['name' => 'Fri-Mar-2025', 'birthday' => Date::parse('2025-03-21 00:00:00'), 'month' => 'March', 'year' => 2025, 'day_of_week' => 'Friday', 'day' => 21],
        ['name' => 'Tue-Apr-2025', 'birthday' => Date::parse('2025-04-08 00:00:00'), 'month' => 'April', 'year' => 2025, 'day_of_week' => 'Tuesday', 'day' => 8],
        ['name' => 'Fri-May-2025', 'birthday' => Date::parse('2025-05-23 00:00:00'), 'month' => 'May', 'year' => 2025, 'day_of_week' => 'Friday', 'day' => 23],
        ['name' => 'Thu-Jun-2025', 'birthday' => Date::parse('2025-06-12 00:00:00'), 'month' => 'June', 'year' => 2025, 'day_of_week' => 'Thursday', 'day' => 12],
        ['name' => 'Sat-Jul-2025', 'birthday' => Date::parse('2025-07-19 00:00:00'), 'month' => 'July', 'year' => 2025, 'day_of_week' => 'Saturday', 'day' => 19],

        ['name' => 'Thu-Aug-2024', 'birthday' => Date::parse('2024-08-01 00:00:00'), 'month' => 'August', 'year' => 2024, 'day_of_week' => 'Thursday', 'day' => 1],
        ['name' => 'Sat-Sep-2024', 'birthday' => Date::parse('2024-09-14 00:00:00'), 'month' => 'September', 'year' => 2024, 'day_of_week' => 'Saturday', 'day' => 14],
        ['name' => 'Tue-Oct-2024', 'birthday' => Date::parse('2024-10-01 00:00:00'), 'month' => 'October', 'year' => 2024, 'day_of_week' => 'Tuesday', 'day' => 1],
        ['name' => 'Thu-Nov-2024', 'birthday' => Date::parse('2024-11-28 00:00:00'), 'month' => 'November', 'year' => 2024, 'day_of_week' => 'Thursday', 'day' => 28],
        ['name' => 'Tue-Dec-2024', 'birthday' => Date::parse('2024-12-17 00:00:00'), 'month' => 'December', 'year' => 2024, 'day_of_week' => 'Tuesday', 'day' => 17],

        // Additional days of the month
        ['name' => 'Mon-Jan-2024', 'birthday' => Date::parse('2024-01-15 00:00:00'), 'month' => 'January', 'year' => 2024, 'day_of_week' => 'Monday', 'day' => 15],
        ['name' => 'Tues-Jan-2024', 'birthday' => Date::parse('2024-01-23 00:00:00'), 'month' => 'January', 'year' => 2024, 'day_of_week' => 'Tuesday', 'day' => 23],
        ['name' => 'Mon-Jan-2024', 'birthday' => Date::parse('2024-01-29 00:00:00'), 'month' => 'January', 'year' => 2024, 'day_of_week' => 'Monday', 'day' => 29],
        ['name' => 'Sat-Feb-2024', 'birthday' => Date::parse('2024-02-10 00:00:00'), 'month' => 'February', 'year' => 2024, 'day_of_week' => 'Saturday', 'day' => 10],
        ['name' => 'Mon-Mar-2024', 'birthday' => Date::parse('2024-03-25 00:00:00'), 'month' => 'March', 'year' => 2024, 'day_of_week' => 'Monday', 'day' => 25],
    ]);

    $results = DB::table('users')->whereWeekday('birthday', '=', Carbon::parse('2024-01-15'))->get();
    expect($results)->toHaveCount(5)
        ->and($results->pluck('day_of_week')->unique()->first())->toBe('Monday');

    $results = DB::table('users')->whereWeekday('birthday', '=', 1)->get();
    expect($results)->toHaveCount(5)
        ->and($results->pluck('day_of_week')->unique()->first())->toBe('Monday');

    $results = DB::table('users')->whereWeekday('birthday', '=', 3)->get();
    expect($results)->toHaveCount(2)
        ->and($results->pluck('day_of_week')->unique()->first())->toBe('Wednesday');

    $results = DB::table('users')->whereWeekday('birthday', '=', 3)->orWhereWeekday('birthday', '=', 1)->get();
    expect($results)->toHaveCount(7)
        ->and($results->pluck('day_of_week'))->toContain('Monday', 'Wednesday');

    $results = DB::table('users')->whereWeekday('birthday', '!=', 1)->get();
    expect($results)->toHaveCount(26)
        ->and($results->pluck('day_of_week'))->not->toContain('Monday');

    $results = DB::table('users')->whereMonth('birthday', '=', 1)->get();
    expect($results)->toHaveCount(12)
        ->and($results->pluck('month'))->toContain('January');

    $results = DB::table('users')->whereMonth('birthday', '!=', 1)->get();
    expect($results)->toHaveCount(19)
        ->and($results->pluck('month'))->not->toContain('January');

    $results = DB::table('users')->whereDay('birthday', '=', 20)->get();
    expect($results)->toHaveCount(1)
        ->and($results->pluck('day'))->toContain(20);

    $results = DB::table('users')->whereDay('birthday', '!=', 20)->get();
    expect($results)->toHaveCount(30)
        ->and($results->pluck('day'))->not->toContain(20);

    $results = DB::table('users')->whereDay('birthday', '>', 20)->get();
    expect($results)->toHaveCount(8)
        ->and($results->pluck('day'))->not->toContain(20);

    $results = DB::table('users')->whereYear('birthday', '=', 2023)->get();
    expect($results)->toHaveCount(7)
        ->and($results->pluck('year'))->toContain(2023);

    $results = DB::table('users')->whereYear('birthday', '!=', 2023)->get();
    expect($results)->toHaveCount(24)
        ->and($results->pluck('year'))->not->toContain(2023);

});

it('pushes values to array fields in a document', function () {
    $id = DB::table('users')->insertGetId([
        'name' => 'John Doe',
        'tags' => [],
        'messages' => [],
    ]);

    DB::table('users')->where('id', $id)->push('tags', 'tag1');

    $user = DB::table('users')->find($id);
    expect($user['tags'])->toBeArray()->toHaveCount(1)
        ->and($user['tags'][0])->toBe('tag1');

    DB::table('users')->where('id', $id)->push('tags', 'tag2');
    $user = DB::table('users')->find($id);
    expect($user['tags'])->toHaveCount(2)
        ->and($user['tags'][1])->toBe('tag2');

    // Add duplicate
    DB::table('users')->where('id', $id)->push('tags', 'tag2');
    $user = DB::table('users')->find($id);
    expect($user['tags'])->toHaveCount(3);

    // Add unique
    DB::table('users')->where('id', $id)->push('tags', 'tag1', true);
    $user = DB::table('users')->find($id);
    expect($user['tags'])->toHaveCount(3);

    $message = ['from' => 'Jane', 'body' => 'Hi John'];
    DB::table('users')->where('id', $id)->push('messages', $message);
    $user = DB::table('users')->find($id);
    expect($user['messages'])->toBeArray()->toHaveCount(1)
        ->and($user['messages'][0])->toBe($message);

});

it('pulls values from array fields in a document', function () {
    $message1 = ['from' => 'Jane', 'body' => 'Hi John'];
    $message2 = ['from' => 'Mark', 'body' => 'Hi John'];

    $id = DB::table('users')->insertGetId([
        'name' => 'John Doe',
        'tags' => ['tag1', 'tag2', 'tag3', 'tag4'],
        'messages' => [$message1, $message2],
    ]);

    DB::table('users')->where('id', $id)->pull('tags', 'tag3');

    $user = DB::table('users')->find($id);
    expect($user['tags'])->toBeArray()->toHaveCount(3)
        ->and($user['tags'][2])->toBe('tag4');

    DB::table('users')->where('id', $id)->pull('messages', [$message1]);

    $user = DB::table('users')->find($id);
    expect($user['messages'])->toBeArray()->toHaveCount(1);

});

it('increments and decrements user age', function () {
    DB::table('users')->insert([
        ['name' => 'John Doe', 'age' => 30, 'note' => 'adult'],
        ['name' => 'Jane Doe', 'age' => 10, 'note' => 'minor'],
        ['name' => 'Robert Roe', 'age' => null],
        ['name' => 'Mark Moe'],
    ]);

    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(30);

    DB::table('users')->where('name', 'John Doe')->increment('age');
    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(31);

    DB::table('users')->where('name', 'John Doe')->decrement('age');
    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(30);

    DB::table('users')->where('name', 'John Doe')->increment('age', 5);
    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(35);

    DB::table('users')->where('name', 'John Doe')->decrement('age', 5);
    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(30);

    DB::table('users')->where('name', 'Jane Doe')->increment('age', 10, ['note' => 'adult']);
    $user = DB::table('users')->where('name', 'Jane Doe')->first();
    expect($user['age'])->toBe(20)
        ->and($user['note'])->toBe('adult');

    DB::table('users')->where('name', 'John Doe')->decrement('age', 20, ['note' => 'minor']);
    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(10)
        ->and($user['note'])->toBe('minor');

});

it('verifies cursor returns lazy collection and checks names', function () {
    $data = [
        ['name' => 'fork', 'tags' => ['sharp', 'pointy']],
        ['name' => 'spoon', 'tags' => ['round', 'bowl']],
        ['name' => 'spork', 'tags' => ['sharp', 'pointy', 'round', 'bowl']],
    ];
    DB::table('items')->insert($data);

    $results = DB::table('items')->orderBy('name.keyword', 'asc')->cursor();

    expect($results)->toBeInstanceOf(Generator::class);
    foreach ($results as $i => $result) {
        expect($result['name'])->toBe($data[$i]['name']);
    }
});

it('increments each specified field by respective values', function () {
    DB::table('users')->insert([
        ['name' => 'John Doe', 'age' => 30, 'note' => 5],
        ['name' => 'Jane Doe', 'age' => 10, 'note' => 6],
        ['name' => 'Robert Roe', 'age' => null],
    ]);

    DB::table('users')->incrementEach([
        'age' => 1,
        'note' => 2,
    ]);

    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(31)
        ->and($user['note'])->toBe(7);

    $user = DB::table('users')->where('name', 'Jane Doe')->first();
    expect($user['age'])->toBe(11)
        ->and($user['note'])->toBe(8);

    $user = DB::table('users')->where('name', 'Robert Roe')->first();
    expect($user['age'])->toBe(1)
        ->and($user['note'])->toBe(2);

    DB::table('users')->where('name', 'Jane Doe')->incrementEach([
        'age' => 1,
        'note' => 2,
    ], ['extra' => 'foo']);

    $user = DB::table('users')->where('name', 'Jane Doe')->first();
    expect($user['age'])->toBe(12)
        ->and($user['note'])->toBe(10)
        ->and($user['extra'])->toBe('foo');

    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(31)
        ->and($user['note'])->toBe(7)
        ->and($user)->not->toHaveProperty('extra');

    DB::table('users')->decrementEach([
        'age' => 1,
        'note' => 2,
    ], ['extra' => 'foo']);

    $user = DB::table('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toBe(30)
        ->and($user['note'])->toBe(5)
        ->and($user['extra'])->toBe('foo');
});

it('validates increments each values', function () {
    DB::table('users')->insert([
        ['name' => 'John Doe', 'age' => 30, 'note' => 5],
        ['name' => 'Jane Doe', 'age' => 10, 'note' => 6],
        ['name' => 'Robert Roe', 'age' => null],
    ]);

    DB::table('users')->incrementEach([
        'age' => 'test',
        'note' => 2,
    ]);

})->throws(InvalidArgumentException::class);

it('validates increments each columns', function () {
    DB::table('users')->insert([
        ['name' => 'John Doe', 'age' => 30, 'note' => 5],
        ['name' => 'Jane Doe', 'age' => 10, 'note' => 6],
        ['name' => 'Robert Roe', 'age' => null],
    ]);

    DB::table('users')->incrementEach([
        ['test' => 'test'],
        'note' => 2,
    ]);

})->throws(InvalidArgumentException::class);

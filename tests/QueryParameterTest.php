<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    Event::fake();
    User::executeSchema();
});

it('tests save without refresh', function () {

    $user = new User;
    $user->name = 'John Doe';
    $user->title = 'admin';
    $user->age = 35;
    $user->withoutRefresh()->save();

    Event::assertDispatched(function (QueryExecuted $event) {
        $event->sql = json_decode($event->sql, true);

        return expect($event->sql['refresh'])->toBeFalse();
    });

});

it('tests update without refresh', function () {

    $user = new User;
    $user->name = 'John Doe';
    $user->title = 'admin';
    $user->age = 35;
    $user->save();

    $firstEvent = Event::dispatched(QueryExecuted::class, function (QueryExecuted $event) {
        if (is_string($event->sql)) {
            $event->sql = json_decode($event->sql, true);
        }

        return isset($event->sql['refresh']) && $event->sql['refresh'] === true;
    });

    expect($firstEvent)->not->toBeEmpty();

    $user->age = 45;
    $user->withoutRefresh()->save();

    // Capture and assert the second QueryExecuted event
    $secondEvent = Event::dispatched(QueryExecuted::class, function (QueryExecuted $event) {
        if (is_string($event->sql)) {
            $event->sql = json_decode($event->sql, true);
        }

        return isset($event->sql['refresh']) && $event->sql['refresh'] === false;
    });

    expect($secondEvent)->not->toBeEmpty();

});

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

    Event::assertDispatched(function (QueryExecuted $event) {
        $event->sql = json_decode($event->sql, true);

        return expect($event->sql['refresh'])->toBeFalse();
    });

});

it('tests create without refresh', function () {

    User::withoutRefresh()->create([
        'name' => 'John Doe',
        'age' => 35,
        'title' => 'admin',
    ]);

    Event::assertDispatched(function (QueryExecuted $event) {
        $event->sql = json_decode($event->sql, true);

        return expect($event->sql['refresh'])->toBeFalse();
    });
});

it('tests firstOrCreate without refresh', function () {

    $user = User::withoutRefresh()->firstOrCreate([
        'name' => 'John Doe',
        'age' => 35,
        'title' => 'admin',
    ]);

    expect($user['_id'])->not->toBeEmpty();

    $insertEvent = Event::dispatched(QueryExecuted::class, function (QueryExecuted $event) {
        $event->sql = json_decode($event->sql, true);

        return isset($event->sql['refresh']) && $event->sql['refresh'] === false;
    });

    expect($insertEvent)->not->toBeEmpty();
});

it('deletes users with specific conditions', function () {

    User::insert([
        ['name' => 'John Doe', 'age' => 35, 'title' => 'admin'],
        ['name' => 'Jane Doe', 'age' => 33, 'title' => 'admin'],
        ['name' => 'Harry Hoe', 'age' => 13, 'title' => 'user'],
        ['name' => 'Robert Roe', 'age' => 37, 'title' => 'user'],
        ['name' => 'Mark Moe', 'age' => 23, 'title' => 'user'],
        ['name' => 'Brett Boe', 'age' => 35, 'title' => 'user'],
        ['name' => 'Tommy Toe', 'age' => 33, 'title' => 'user'],
        ['name' => 'Yvonne Yoe', 'age' => 35, 'title' => 'admin'],
    ]);

    expect(User::where('title', 'admin')->count())->toBe(3);
    User::proceedOnConflicts()->where('title', 'admin')->delete();

    $proceedEvent = Event::dispatched(QueryExecuted::class, function (QueryExecuted $event) {
        $event->sql = json_decode($event->sql, true);

        return isset($event->sql['conflicts']) && $event->sql['conflicts'] === 'proceed';
    });
    expect($proceedEvent)->not->toBeEmpty()
        ->and(User::where('title', 'admin')->count())->toBe(0)
        ->and(User::count())->toBe(5);

});

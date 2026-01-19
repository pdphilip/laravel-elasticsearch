<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PDPhilip\Elasticsearch\Tests\Models\Item;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    User::executeSchema();
    Item::executeSchema();
});

it('aggregates distinct value, ordered by _count desc [ES default]', function () {

    DB::table('items')->insert([
        ['name' => 'sword', 'type' => 'sharp', 'amount' => 34, 'stock' => 1],
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34, 'stock' => 1],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20, 'stock' => 5],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3, 'stock' => 15],
        ['name' => 'ball', 'type' => 'round', 'amount' => 14, 'stock' => 7],
        ['name' => 'teddy', 'type' => 'fluffy', 'amount' => 3, 'stock' => 5],
    ]);

    $items = Item::distinct('type');
    expect($items)->toHaveCount(3)
        ->and($items[0]['type'])->toBe('sharp')
        ->and($items[2]['type'])->toBe('fluffy');
});

it('aggregates distinct value, ordered by _count asc', function () {

    DB::table('items')->insert([
        ['name' => 'sword', 'type' => 'sharp', 'amount' => 34, 'stock' => 1],
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34, 'stock' => 1],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20, 'stock' => 5],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3, 'stock' => 15],
        ['name' => 'ball', 'type' => 'round', 'amount' => 14, 'stock' => 7],
        ['name' => 'teddy', 'type' => 'fluffy', 'amount' => 3, 'stock' => 5],
    ]);

    $items = Item::orderBy('_count')->distinct('type');
    expect($items)->toHaveCount(3)
        ->and($items[0]['type'])->toBe('fluffy')
        ->and($items[2]['type'])->toBe('sharp');

});

it('aggregates distinct value with count', function () {

    DB::table('items')->insert([
        ['name' => 'sword', 'type' => 'sharp', 'amount' => 34, 'stock' => 1],
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34, 'stock' => 1],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20, 'stock' => 5],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3, 'stock' => 15],
        ['name' => 'ball', 'type' => 'round', 'amount' => 14, 'stock' => 7],
        ['name' => 'teddy', 'type' => 'fluffy', 'amount' => 3, 'stock' => 5],
    ]);

    $items = Item::distinct('type', true);
    expect($items)->toHaveCount(3)
        ->and($items[0]['type'])->toBe('sharp')
        ->and($items[0]['type_count'])->toBe(3)
        ->and($items[2]['type'])->toBe('fluffy')
        ->and($items[2]['type_count'])->toBe(1);
});

it('aggregates distinct values (cumulative fields), with count', function () {

    DB::table('items')->insert([
        ['name' => 'teddy', 'type' => 'fluffy', 'amount' => 3, 'stock' => 33],
        ['name' => 'sword', 'type' => 'sharp', 'amount' => 34, 'stock' => 10],
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34, 'stock' => 10],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20, 'stock' => 25],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3, 'stock' => 25],
        ['name' => 'ball', 'type' => 'round', 'amount' => 14, 'stock' => 33],

    ]);

    $items = Item::distinct(['type', 'stock'], true);
    expect($items)->toHaveCount(5)
        ->and($items[0]['type'])->toBe('sharp')
        ->and($items[0]['stock'])->toBe(10)
        ->and($items[0]['stock_count'])->toBe(2)
        ->and($items[4]['type'])->toBe('fluffy')
        ->and($items[4]['stock'])->toBe(33)
        ->and($items[4]['stock_count'])->toBe(1);
});

it('aggregates distinct value returning top 2 only', function () {

    DB::table('items')->insert([
        ['name' => 'sword alpha', 'type' => 'sharp', 'amount' => 34, 'stock' => 1],
        ['name' => 'sword beta', 'type' => 'sharp', 'amount' => 34, 'stock' => 10],
        ['name' => 'teddy', 'type' => 'fluffy', 'amount' => 3, 'stock' => 5],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3, 'stock' => 15],
        ['name' => 'sword charlie', 'type' => 'sharp', 'amount' => 34, 'stock' => 111],
        ['name' => 'sword delta', 'type' => 'sharp', 'amount' => 34, 'stock' => 12],
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34, 'stock' => 1],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20, 'stock' => 5],
        ['name' => 'golf ball', 'type' => 'round', 'amount' => 14, 'stock' => 7],
        ['name' => 'ball', 'type' => 'round', 'amount' => 14, 'stock' => 7],

    ]);

    $items = Item::limit(2)->distinct('type');
    expect($items)->toHaveCount(2)
        ->and($items[0]['type'])->toBe('sharp')
        ->and($items[1]['type'])->toBe('round');
});

it('aggregates distinct value with relations, with count', function () {

    $user = User::create(['name' => 'John Doe']);
    Item::create(['type' => 'knife', 'user_id' => $user->id]);
    Item::create(['type' => 'shield', 'user_id' => $user->id]);

    $user2 = User::create(['name' => 'Jane Doe']);
    Item::create(['type' => 'teddy', 'user_id' => $user2->id]);
    Item::create(['type' => 'ball', 'user_id' => $user2->id]);
    Item::create(['type' => 'sword', 'user_id' => $user2->id]);

    Item::create(['type' => 'bag', 'user_id' => null]);

    $itemUsers = Item::with('user')->distinct('user_id', true);
    expect($itemUsers)->not()->toHaveCount(3)
        ->toHaveCount(2)
        ->and($itemUsers[0]['user_id_count'])->toBe(3)
        ->and($itemUsers[0]->user->name)->toBe('Jane Doe')
        ->and($itemUsers[1]['user_id_count'])->toBe(2)
        ->and($itemUsers[1]->user->name)->toBe('John Doe');
});

it('aggregates bulk distinct values in one call, with count', function () {

    DB::table('items')->insert([
        ['name' => 'teddy', 'type' => 'fluffy', 'amount' => 3, 'stock' => 33],
        ['name' => 'sword', 'type' => 'sharp', 'amount' => 34, 'stock' => 33],
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34, 'stock' => 10],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20, 'stock' => 25],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3, 'stock' => 25],
        ['name' => 'ball', 'type' => 'round', 'amount' => 14, 'stock' => 33],

    ]);

    $items = Item::bulkDistinct(['type', 'stock'], true);
    expect($items)
        ->toHaveCount(6)
        ->and($items[0]['type'])->toBe('sharp')
        ->and($items[0]['type_count'])->toBe(3)
        ->and($items[2]['type'])->toBe('fluffy')
        ->and($items[2]['type_count'])->toBe(1)
        ->and($items[3]['stock'])->toBe(33)
        ->and($items[3]['stock_count'])->toBe(3)
        ->and($items[5]['stock'])->toBe(10)
        ->and($items[5]['stock_count'])->toBe(1);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PDPhilip\Elasticsearch\Tests\Models\Item;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    User::executeSchema();
    Item::executeSchema();

    DB::table('items')->insert([
        ['name' => 'sword alpha', 'type' => 'sharp', 'amount' => 1500, 'stock' => 1],
        ['name' => 'sword beta', 'type' => 'sharp', 'amount' => 900, 'stock' => 10],
        ['name' => 'teddy', 'type' => 'fluffy', 'amount' => 10, 'stock' => 5],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3, 'stock' => 15],
        ['name' => 'sword charlie', 'type' => 'sharp', 'amount' => 350, 'stock' => 111],
        ['name' => 'sword delta', 'type' => 'sharp', 'amount' => 99, 'stock' => 12],
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 8, 'stock' => 1],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 6, 'stock' => 5],
        ['name' => 'golf ball', 'type' => 'round', 'amount' => 14, 'stock' => 7],
        ['name' => 'ball', 'type' => 'round', 'amount' => 14, 'stock' => 7],
    ]);

});

it('groups by ranges using arrays for ranges', function () {

    $groups = Item::groupByRanges('stock', [
        [null, 5],
        [5, 15],
        [15, null],
    ])->get();
    expect($groups)->toHaveCount(3)
        ->and($groups[0]['key'])->toBe('*-5.0')
        ->and($groups[1]['key'])->toBe('5.0-15.0')
        ->and($groups[2]['key'])->toBe('15.0-*')
        ->and($groups[0]['count'])->toBe(2)
        ->and($groups[1]['count'])->toBe(6)
        ->and($groups[2]['count'])->toBe(2);

});
it('groups by ranges using associative arrays for ranges', function () {

    $groups = Item::groupByRanges('stock', [
        [
            'to' => 5,
        ],
        [
            'from' => 5,
            'to' => 15,
        ],
        [
            'from' => 15,
        ],
    ])->get();
    expect($groups)->toHaveCount(3)
        ->and($groups[0]['key'])->toBe('*-5.0')
        ->and($groups[1]['key'])->toBe('5.0-15.0')
        ->and($groups[2]['key'])->toBe('15.0-*')
        ->and($groups[0]['count'])->toBe(2)
        ->and($groups[1]['count'])->toBe(6)
        ->and($groups[2]['count'])->toBe(2);
});

it('groups by ranges using associative arrays for ranges with custom keys', function () {

    $groups = Item::groupByRanges('stock', [
        [
            'key' => 'low-stock',
            'to' => 5,
        ],
        [
            'key' => 'medium-stock',
            'from' => 5,
            'to' => 15,
        ],
        [
            'key' => 'high-stock',
            'from' => 15,
        ],
    ])->get();
    expect($groups)->toHaveCount(3)
        ->and($groups[0]['key'])->toBe('low-stock')
        ->and($groups[1]['key'])->toBe('medium-stock')
        ->and($groups[2]['key'])->toBe('high-stock')
        ->and($groups[0]['count'])->toBe(2)
        ->and($groups[1]['count'])->toBe(6)
        ->and($groups[2]['count'])->toBe(2);
});

it('groups by ranges and aggregates', function () {

    $groups = Item::groupByRanges('stock', [
        [
            'key' => 'low-stock',
            'to' => 5,
        ],
        [
            'key' => 'medium-stock',
            'from' => 5,
            'to' => 15,
        ],
        [
            'key' => 'high-stock',
            'from' => 15,
        ],
    ])->agg(['min', 'max', 'count'], 'amount');
    expect($groups)->toHaveCount(3)
        ->and($groups[1]['key'])->toBe('medium-stock')
        ->and($groups[1]['count'])->toBe(6)
        ->and($groups[1]['count'])->toBe($groups[1]['count_amount']) // the aggregate `count_amount` reflects the same as the group count
        ->and((int) $groups[1]['min_amount'])->toBe(6)
        ->and((int) $groups[1]['max_amount'])->toBe(900);
});

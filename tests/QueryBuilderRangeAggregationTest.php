<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PDPhilip\Elasticsearch\Tests\Models\Item;

beforeEach(function () {
    Item::executeSchema();

    DB::table('items')->insert([
        ['name' => 'sword alpha', 'type' => 'sharp', 'price' => 1500, 'stock' => 1, 'last_sale_at' => '2024-01-01'],
        ['name' => 'sword beta', 'type' => 'sharp', 'price' => 900, 'stock' => 10, 'last_sale_at' => '2024-02-01'],
        ['name' => 'teddy', 'type' => 'fluffy', 'price' => 10, 'stock' => 5, 'last_sale_at' => '2024-03-01'],
        ['name' => 'spoon', 'type' => 'round', 'price' => 3, 'stock' => 15, 'last_sale_at' => '2024-04-01'],
        ['name' => 'sword charlie', 'type' => 'sharp', 'price' => 350, 'stock' => 111, 'last_sale_at' => '2024-05-01'],
        ['name' => 'sword delta', 'type' => 'sharp', 'price' => 99, 'stock' => 12, 'last_sale_at' => '2024-06-01'],
        ['name' => 'knife', 'type' => 'sharp', 'price' => 8, 'stock' => 1, 'last_sale_at' => '2024-07-01'],
        ['name' => 'fork', 'type' => 'sharp', 'price' => 6, 'stock' => 5, 'last_sale_at' => '2024-08-01'],
        ['name' => 'golf ball', 'type' => 'round', 'price' => 14, 'stock' => 7, 'last_sale_at' => '2024-09-01'],
        ['name' => 'ball', 'type' => 'round', 'price' => 14, 'stock' => 7, 'last_sale_at' => '2024-10-01'],
    ]);

});

it('groups by ranges using arrays for ranges', function () {

    $groups = Item::groupByRanges('stock', [
        [null, 5],
        [5, 15],
        [15, null],
    ])->get();
    expect($groups)->toHaveCount(3)
        ->and($groups[0]['count_stock_range_*-5.0'])->toBe(2)
        ->and($groups[1]['count_stock_range_5.0-15.0'])->toBe(6)
        ->and($groups[2]['count_stock_range_15.0-*'])->toBe(2);

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
        ->and($groups[0]['count_stock_range_*-5.0'])->toBe(2)
        ->and($groups[1]['count_stock_range_5.0-15.0'])->toBe(6)
        ->and($groups[2]['count_stock_range_15.0-*'])->toBe(2);
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
        ->and($groups[0]['count_stock_range_low-stock'])->toBe(2)
        ->and($groups[1]['count_stock_range_medium-stock'])->toBe(6)
        ->and($groups[2]['count_stock_range_high-stock'])->toBe(2);
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
    ])->agg(['min', 'max', 'count'], 'price');
    expect($groups)->toHaveCount(3)
        ->and($groups[1]['count_stock_range_medium-stock'])->toBe(6)
        ->and($groups[1]['count_stock_range_medium-stock'])->toBe($groups[1]['count_price_stock_range_medium-stock']) // the aggregate `count_amount` reflects the same as the group count
        ->and((int) $groups[1]['min_price_stock_range_medium-stock'])->toBe(6)
        ->and((int) $groups[1]['max_price_stock_range_medium-stock'])->toBe(900);
});

it('groups by ranges and aggregates with original bucket in meta', function () {

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
    ])->agg(['min', 'max', 'count'], 'price');
    $bucketMediumStock = $groups[1]->getMetaValue('bucket');
    expect($bucketMediumStock)->toHaveCount(7)
        ->and($bucketMediumStock['key'])->toBe('medium-stock')
        ->and((int) $bucketMediumStock['from'])->toBe(5)
        ->and((int) $bucketMediumStock['to'])->toBe(15)
        ->and((int) $bucketMediumStock['doc_count'])->toBe(6)
        ->and((int) $bucketMediumStock['count_price']['value'])->toBe(6)
        ->and((int) $bucketMediumStock['min_price']['value'])->toBe(6)
        ->and((int) $bucketMediumStock['max_price']['value'])->toBe(900);
});

it('groups by date range and aggregates', function () {

    $ranges = [
        ['to' => '2024-05-01', 'key' => 'before-campaign'],
        ['from' => '2024-05-01', 'key' => 'after-campaign'],
    ];
    $options = ['format' => 'yyyy-MM-dd'];
    $groups = Item::groupByDateRanges('last_sale_at', $ranges, $options)->agg(['min', 'max', 'count'], 'price');
    expect($groups)->toHaveCount(2)
        ->and((int) $groups[0]['count_last_sale_at_range_before-campaign'])->toBe(4)
        ->and((int) $groups[0]['min_price_last_sale_at_range_before-campaign'])->toBe(3)
        ->and((int) $groups[0]['max_price_last_sale_at_range_before-campaign'])->toBe(1500)
        ->and((int) $groups[0]['count_price_last_sale_at_range_before-campaign'])->toBe(4)
        ->and((int) $groups[1]['count_last_sale_at_range_after-campaign'])->toBe(6)
        ->and((int) $groups[1]['min_price_last_sale_at_range_after-campaign'])->toBe(6)
        ->and((int) $groups[1]['max_price_last_sale_at_range_after-campaign'])->toBe(350)
        ->and((int) $groups[1]['count_price_last_sale_at_range_after-campaign'])->toBe(6);
});

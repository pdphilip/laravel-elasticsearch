<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PDPhilip\Elasticsearch\Tests\Models\Item;
use PDPhilip\Elasticsearch\Tests\Models\User;

beforeEach(function () {
    User::executeSchema();
    Item::executeSchema();

    DB::table('items')->insert([
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34, 'stock' => 1],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20, 'stock' => 5],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3, 'stock' => 15],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 14, 'stock' => 7],
    ]);
});

it('aggregate multiple metrics', function () {

    $items = DB::table('items')->min(['amount', 'stock']);
    expect($items)->toHaveCount(2)
        ->and($items)->toHaveKeys(['min_amount', 'min_stock'])
        ->and($items['min_amount'])->toBe(3.0)
        ->and($items['min_stock'])->toBe(1.0);
});

it('bucket and then aggregate a single metric', function () {

    $items = DB::table('items')->groupBy('name')->boxplot('amount');
    expect($items)->toHaveCount(3)
        ->and($items[0]['name'])->toBe('fork')
        ->and($items[0]['boxplot_amount'])->toHaveCount(7);

    $items = DB::table('items')->groupBy('name')->min('amount');
    expect($items)->toHaveCount(3)
        ->and($items[0]['name'])->toBe('fork')
        ->and($items[0]['min_amount'])->toBe(20.0);

    $items = DB::table('items')->groupBy('name', 'type')->min('stock');
    expect($items)->toHaveCount(3)
        ->and($items[0]['name'])->toBe('fork')
        ->and($items[0]['type'])->toBe('sharp')
        ->and($items[0]['min_stock'])->toBe(5.0);
});

it('bucket and then aggregate multiple metrics', function () {

    $items = DB::table('items')->groupBy('name')->boxplot(['amount', 'stock']);
    expect($items)->toHaveCount(3)
        ->and($items[0]['name'])->toBe('fork')
        ->and($items[0]['boxplot_amount'])->toHaveCount(7)
        ->and($items[0]['boxplot_stock'])->toHaveCount(7);

    $items = DB::table('items')->groupBy('name')->min(['amount', 'stock']);
    expect($items)->toHaveCount(3)
        ->and($items[0]['name'])->toBe('fork')
        ->and($items[0]['min_amount'])->toBe(20.0)
        ->and($items[0]['min_stock'])->toBe(5.0);

    $items = DB::table('items')->groupBy('name', 'type')->min(['amount', 'stock']);
    expect($items)->toHaveCount(3)
        ->and($items[0]['name'])->toBe('fork')
        ->and($items[0]['type'])->toBe('sharp')
        ->and($items[0]['min_amount'])->toBe(20.0)
        ->and($items[0]['min_stock'])->toBe(5.0);
});

it('can get metric aggregations', function () {

    $items = DB::table('items')->matrix('amount');
    expect($items)->toHaveCount(8)
        ->and($items)->toHaveKeys(['count', 'mean', 'variance', 'skewness', 'kurtosis']);

    $items = DB::table('items')->boxplot('amount');
    expect($items)->toHaveCount(7)
        ->and($items)->toHaveKeys(['min', 'max', 'q1', 'q2', 'q3', 'lower', 'upper']);

    $items = DB::table('items')->stats('amount');
    expect($items)->toHaveCount(5)
        ->and($items)->toHaveKeys(['count', 'min', 'max', 'avg', 'sum']);

    $items = DB::table('items')->count('amount');
    expect($items)->toBe(4);

    $items = DB::table('items')->cardinality('type.keyword');
    expect($items)->toBe(2);

    $items = DB::table('items')->max('amount');
    expect($items)->toBe(34.0);

    $items = DB::table('items')->min('amount');
    expect($items)->toBe(3.0);

    $items = DB::table('items')->sum('amount');
    expect($items)->toBe(71.0);

    $items = DB::table('items')->extendedStats('amount');
    expect($items)->toHaveCount(13)
        ->and($items)->toHaveKeys(['count', 'min', 'max', 'avg', 'sum', 'sum_of_squares', 'variance', 'variance_population', 'variance_sampling', 'std_deviation', 'std_deviation_population', 'std_deviation_sampling', 'std_deviation_bounds']);

    $items = DB::table('items')->medianAbsoluteDeviation('amount');
    expect($items)->toBe(8.5);

    $items = DB::table('items')->percentiles('amount');
    expect($items)->toHaveCount(7)
        ->and($items)->toHaveKeys(['1.0', '5.0', '25.0']);

    $items = DB::table('items')->stringStats('name.keyword');
    expect($items)->toHaveCount(5)
        ->and($items)->toHaveKeys(['count', 'min_length', 'max_length', 'avg_length', 'entropy']);
});

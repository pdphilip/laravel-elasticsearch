<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Schema\Schema;
use Workbench\App\Models\Product;

beforeEach(function () {
    Schema::deleteIfExists('products');
    Schema::create('products', function ($index) {
        $index->text('name');
        $index->keyword('product_id');
        $index->keyword('name');
        $index->keyword('color');
        $index->float('price');
        $index->integer('status');
        $index->geo('manufacturer.location');
        $index->date('created_at');
        $index->date('updated_at');
        $index->date('deleted_at');
    });
});

test('filter products within a geo box', function () {
    Product::factory()->count(3)->state(['manufacturer' => ['location' => ['lat' => 5, 'lon' => 5]]])->create();
    Product::factory()->count(2)->state(['manufacturer' => ['location' => ['lat' => 15, 'lon' => -15]]])->create();

    $topLeft = [-10, 10];
    $bottomRight = [10, -10];
    $products = Product::where('status', 7)->filterGeoBox('manufacturer.location', $topLeft, $bottomRight)->get();
    expect($products)->toHaveCount(3); // Expecting only the first three within the box
})->todo();

test('filter products close to a specific point', function () {
    Product::factory()->state(['manufacturer' => ['location' => ['lat' => 0, 'lon' => 0]]])->create();

    $point = [0, 0];
    $distance = '20km';
    $products = Product::where('status', 7)->filterGeoPoint('manufacturer.location', $distance, $point)->get();
    expect($products)->toHaveCount(1);
})->todo();

test('search for products by exact name', function () {
    Product::factory()->state(['name' => 'John Smith'])->create();

    $products = Product::whereExact('name', 'John Smith')->get();
    expect($products->first()->name)->toEqual('John Smith');
});

test('search for products by phrase in description', function () {
    Product::factory()->state(['description' => 'loves espressos'])->create();

    $products = Product::wherePhrase('description', 'loves espressos')->get();
    expect($products->first()->description)->toContain('loves espressos');
});

test('search for products where description starts with a prefix', function () {
    Product::factory()->state(['description' => 'loves espresso beans'])->create();

    $products = Product::wherePhrasePrefix('description', 'loves es')->get();
    expect($products->first()->description)->toContain('loves espresso beans');
});

test('query products by timestamp', function () {
    $timestamp = 1713911889521;
    Product::factory()->state(['last_order_ts' => $timestamp])->create();

    $products = Product::whereTimestamp('last_order_ts', '<=', $timestamp)->get();
    expect($products)->toHaveCount(1);
});

test('search for products using regex on color', function () {
    Product::factory()->state(['color' => 'blue'])->create();
    Product::factory()->state(['color' => 'black'])->create();

    $regexProducts = Product::whereRegex('color', 'bl(ue)?(ack)?')->get();
    expect($regexProducts)->toHaveCount(2);
});

test('execute raw DSL query on products', function () {
    Product::factory()->state(['color' => 'silver'])->create();

    $bodyParams = [
        'query' => [
            'match' => [
                'color' => 'silver',
            ],
        ],
    ];
    $products = Product::rawSearch($bodyParams);
    expect($products)->toHaveCount(1);
});

test('perform raw aggregation query', function () {
    Product::factory()->state(['price' => 50])->create();
    Product::factory()->state(['price' => 300])->create();
    Product::factory()->state(['price' => 700])->create();
    Product::factory()->state(['price' => 1200])->create();

    $body = [
        'aggs' => [
            'price_ranges' => [
                'range' => [
                    'field' => 'price',
                    'ranges' => [
                        ['to' => 100],
                        ['from' => 100, 'to' => 500],
                        ['from' => 500, 'to' => 1000],
                        ['from' => 1000],
                    ],
                ],
                'aggs' => [
                    'sales_over_time' => [
                        'date_histogram' => [
                            'field' => 'datetime',
                            'fixed_interval' => '1d',
                        ],
                    ],
                ],
            ],
        ],
    ];
    $results = Product::rawAggregation($body);
    expect($results)->toBeArray()
        ->and(array_keys($results))->toContain('aggregations');
})->todo();

test('convert query to DSL', function () {
    $dslQuery = Product::where('price', '>', 100)->toDSL();

    expect($dslQuery)->toBeArray()
        ->and($dslQuery['body']['query']['bool']['must'][0]['range'])->toBeArray();
});

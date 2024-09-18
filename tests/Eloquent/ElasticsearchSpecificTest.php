<?php

declare(strict_types=1);

  use PDPhilip\Elasticsearch\Collection\ElasticCollection;
  use Workbench\App\Models\Product;

test('filter products within a geo box', function () {

    Product::factory()->count(3)->state(['status' => 7, 'manufacturer' => ['location' => ['lat' => 5, 'lon' => 5]]])->create();
    Product::factory()->count(2)->state(['status' => 7, 'manufacturer' => ['location' => ['lat' => 15, 'lon' => -15]]])->create();
    $topLeft = [-10, 10];
    $bottomRight = [10, -10];
    $products = Product::where('status', 7)->filterGeoBox('manufacturer.location', $topLeft, $bottomRight)->get();
    expect($products)->toHaveCount(3); // Expecting only the first three within the box
});

test('filter products close to a specific point', function () {

    $first = Product::factory()->state(['status' => 7])->create();
    Product::factory()->count(5)->state(['status' => 7])->create();
    $first->manufacturer = ['location' => ['lat' => 0, 'lon' => 0]];
    $first->save();
    $point = [0, 0];
    $distance = '1m';
    $products = Product::where('status', 7)->filterGeoPoint('manufacturer.location', $distance, $point)->get();
    expect($products)->toHaveCount(1);

});

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
  Product::factory(2)->state(['color' => 'silver'])->create();
  Product::factory(1)->state(['color' => 'blue'])->create();

    $bodyParams = [
        'query' => [
            'match' => [
                'color' => 'silver',
            ],
        ],
    ];
    $products = Product::rawSearch($bodyParams);

    expect($products)
      ->toBeInstanceOf(ElasticCollection::class)
      ->toHaveCount(2)
      ->and($products->first())->toBeInstanceOf(Product::class)
      ->and($products->first()['color'])->toBe('silver');
});

test('perform raw aggregation query', function () {
    Product::factory()->state(['price' => 50])->create();
    Product::factory()->state(['price' => 300])->create();
    Product::factory()->state(['price' => 700])->create();
    Product::factory()->state(['price' => 1200])->create();

    $bodyParams = [
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
            ],
        ],
    ];
    $priceBuckets = Product::rawAggregation($bodyParams);
    expect($priceBuckets['price_ranges'][0]['doc_count'])->toBe(1)
        ->and($priceBuckets['price_ranges'][1]['doc_count'])->toBe(1)
        ->and($priceBuckets['price_ranges'][2]['doc_count'])->toBe(1)
        ->and($priceBuckets['price_ranges'][3]['doc_count'])->toBe(1);
});

test('convert query to DSL', function () {
    $dslQuery = Product::where('price', '>', 100)->toDSL();

    expect($dslQuery)->toBeArray()
        ->and($dslQuery['body']['query']['bool']['must'][0]['range'])->toBeArray();
});

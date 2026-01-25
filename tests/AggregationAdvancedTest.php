<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Tests\Models\Product;

beforeEach(function () {
    Product::executeSchema();
});

/*
|--------------------------------------------------------------------------
| Advanced Aggregation Tests
|--------------------------------------------------------------------------
|
| Tests for advanced metric and bucket aggregations beyond basic
| min/max/sum/avg operations.
|
*/

// ----------------------------------------------------------------------
// Stats - Combined statistics
// ----------------------------------------------------------------------

it('computes comprehensive stats for numeric field', function () {
    Product::insert([
        ['name' => 'A', 'price' => 100],
        ['name' => 'B', 'price' => 200],
        ['name' => 'C', 'price' => 300],
        ['name' => 'D', 'price' => 400],
    ]);

    $stats = Product::stats('price');

    expect($stats)->toBeArray()
        ->and($stats['count'])->toBe(4)
        ->and($stats['min'])->toBe(100.0)
        ->and($stats['max'])->toBe(400.0)
        ->and($stats['avg'])->toBe(250.0)
        ->and($stats['sum'])->toBe(1000.0);
});

// ----------------------------------------------------------------------
// Extended Stats - Statistical analysis
// ----------------------------------------------------------------------

it('computes extended statistics including variance and std deviation', function () {
    Product::insert([
        ['name' => 'A', 'price' => 100],
        ['name' => 'B', 'price' => 200],
        ['name' => 'C', 'price' => 300],
        ['name' => 'D', 'price' => 400],
        ['name' => 'E', 'price' => 500],
    ]);

    $stats = Product::extendedStats('price');

    expect($stats)->toBeArray()
        ->and($stats)->toHaveKey('count')
        ->and($stats)->toHaveKey('min')
        ->and($stats)->toHaveKey('max')
        ->and($stats)->toHaveKey('avg')
        ->and($stats)->toHaveKey('sum')
        ->and($stats)->toHaveKey('variance')
        ->and($stats)->toHaveKey('std_deviation')
        ->and($stats)->toHaveKey('std_deviation_bounds');
});

// ----------------------------------------------------------------------
// Boxplot - Statistical distribution
// ----------------------------------------------------------------------

it('computes boxplot statistics', function () {
    Product::insert([
        ['name' => 'A', 'price' => 100],
        ['name' => 'B', 'price' => 200],
        ['name' => 'C', 'price' => 300],
        ['name' => 'D', 'price' => 400],
        ['name' => 'E', 'price' => 500],
        ['name' => 'F', 'price' => 600],
        ['name' => 'G', 'price' => 700],
    ]);

    $boxplot = Product::boxplot('price');

    expect($boxplot)->toBeArray()
        ->and($boxplot)->toHaveKey('min')
        ->and($boxplot)->toHaveKey('max')
        ->and($boxplot)->toHaveKey('q1')  // First quartile
        ->and($boxplot)->toHaveKey('q2')  // Median (second quartile)
        ->and($boxplot)->toHaveKey('q3'); // Third quartile
});

// ----------------------------------------------------------------------
// Cardinality - Distinct count
// ----------------------------------------------------------------------

it('counts distinct values with cardinality', function () {
    Product::insert([
        ['name' => 'A', 'category' => 'tools'],
        ['name' => 'B', 'category' => 'tools'],
        ['name' => 'C', 'category' => 'electronics'],
        ['name' => 'D', 'category' => 'electronics'],
        ['name' => 'E', 'category' => 'furniture'],
    ]);

    $cardinality = Product::cardinality('category');

    expect($cardinality)->toBe(3); // 3 distinct categories
});

// ----------------------------------------------------------------------
// Percentiles - Distribution percentiles
// ----------------------------------------------------------------------

it('computes value percentiles', function () {
    // Insert 100 products with prices 1-100
    $products = [];
    for ($i = 1; $i <= 100; $i++) {
        $products[] = ['name' => "Product $i", 'price' => $i];
    }
    Product::insert($products);

    $percentiles = Product::percentiles('price');

    expect($percentiles)->toBeArray()
        ->and($percentiles)->toHaveKey('1.0')
        ->and($percentiles)->toHaveKey('5.0')
        ->and($percentiles)->toHaveKey('25.0')
        ->and($percentiles)->toHaveKey('50.0')
        ->and($percentiles)->toHaveKey('75.0')
        ->and($percentiles)->toHaveKey('95.0')
        ->and($percentiles)->toHaveKey('99.0');
});

it('computes custom percentiles', function () {
    $products = [];
    for ($i = 1; $i <= 100; $i++) {
        $products[] = ['name' => "Product $i", 'price' => $i];
    }
    Product::insert($products);

    $percentiles = Product::percentiles('price', ['percents' => [10, 50, 90]]);

    expect($percentiles)->toBeArray()
        ->and($percentiles)->toHaveKey('10.0')
        ->and($percentiles)->toHaveKey('50.0')
        ->and($percentiles)->toHaveKey('90.0');
});

// ----------------------------------------------------------------------
// Median Absolute Deviation - Variability measure
// ----------------------------------------------------------------------

it('computes median absolute deviation', function () {
    Product::insert([
        ['name' => 'A', 'price' => 100],
        ['name' => 'B', 'price' => 150],
        ['name' => 'C', 'price' => 200],
        ['name' => 'D', 'price' => 250],
        ['name' => 'E', 'price' => 300],
    ]);

    $mad = Product::medianAbsoluteDeviation('price');

    expect($mad)->toBeNumeric();
});

// ----------------------------------------------------------------------
// String Stats - Text field statistics
// ----------------------------------------------------------------------

it('computes string statistics for text field', function () {
    Product::insert([
        ['name' => 'Widget'],
        ['name' => 'Gadget'],
        ['name' => 'Tool'],
        ['name' => 'Device'],
    ]);

    $stats = Product::stringStats('name.keyword');

    expect($stats)->toBeArray()
        ->and($stats)->toHaveKey('count')
        ->and($stats)->toHaveKey('min_length')
        ->and($stats)->toHaveKey('max_length')
        ->and($stats)->toHaveKey('avg_length')
        ->and($stats)->toHaveKey('entropy');
});

// ----------------------------------------------------------------------
// Matrix Stats - Multi-field correlation
// ----------------------------------------------------------------------

it('computes matrix statistics for multiple fields', function () {
    Product::insert([
        ['name' => 'A', 'price' => 100, 'quantity' => 10],
        ['name' => 'B', 'price' => 200, 'quantity' => 20],
        ['name' => 'C', 'price' => 300, 'quantity' => 15],
        ['name' => 'D', 'price' => 400, 'quantity' => 25],
    ]);

    $matrix = Product::matrix(['price', 'quantity']);
    expect($matrix)->toBeArray()
        ->and($matrix)->toHaveKey('matrix_stats_price')
        ->and($matrix)->toHaveKey('matrix_stats_quantity');
});

// ----------------------------------------------------------------------
// agg() - Multi-metric aggregation
// ----------------------------------------------------------------------

it('computes multiple metrics at once', function () {
    Product::insert([
        ['name' => 'A', 'price' => 100],
        ['name' => 'B', 'price' => 200],
        ['name' => 'C', 'price' => 300],
    ]);

    $results = Product::agg(['min', 'max', 'avg'], 'price');

    expect($results)->toBeArray()
        ->and($results['min_price'])->toBe(100.0)
        ->and($results['max_price'])->toBe(300.0)
        ->and($results['avg_price'])->toBe(200.0);
});

it('computes multiple metrics on multiple columns', function () {
    Product::insert([
        ['name' => 'A', 'price' => 100, 'quantity' => 10],
        ['name' => 'B', 'price' => 200, 'quantity' => 20],
    ]);

    $results = Product::agg(['min', 'max'], ['price', 'quantity']);

    expect($results)->toBeArray()
        ->and($results)->toHaveKey('min_price')
        ->and($results)->toHaveKey('max_price')
        ->and($results)->toHaveKey('min_quantity')
        ->and($results)->toHaveKey('max_quantity');
});

// ----------------------------------------------------------------------
// Bucket Aggregations
// ----------------------------------------------------------------------

it('groups by field with bucket aggregation', function () {
    Product::insert([
        ['name' => 'A', 'category' => 'tools'],
        ['name' => 'B', 'category' => 'tools'],
        ['name' => 'C', 'category' => 'electronics'],
    ]);

    $results = Product::groupBy('category')->getAggregationResults();

    expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($results)->toHaveCount(2);
});

it('groups by multiple fields', function () {
    Product::insert([
        ['name' => 'A', 'category' => 'tools', 'status' => 'active'],
        ['name' => 'B', 'category' => 'tools', 'status' => 'inactive'],
        ['name' => 'C', 'category' => 'electronics', 'status' => 'active'],
    ]);

    $results = Product::groupBy('category', 'status')->getAggregationResults();

    expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

// ----------------------------------------------------------------------
// groupByRanges - Numeric range buckets
// ----------------------------------------------------------------------

it('groups by numeric ranges', function () {
    Product::insert([
        ['name' => 'Cheap', 'price' => 50],
        ['name' => 'Budget', 'price' => 80],
        ['name' => 'Standard', 'price' => 150],
        ['name' => 'Premium', 'price' => 300],
        ['name' => 'Luxury', 'price' => 500],
    ]);

    $results = Product::groupByRanges('price', [
        ['to' => 100, 'key' => 'budget'],
        ['from' => 100, 'to' => 200, 'key' => 'standard'],
        ['from' => 200, 'key' => 'luxury'],
    ])->get()->toArray();

    expect($results)->toHaveCount(3)
        ->and($results[0]['count_price_range_budget'])->toBe(2)  // Cheap, Budget
        ->and($results[1]['count_price_range_standard'])->toBe(1)  // Standard
        ->and($results[2]['count_price_range_luxury'])->toBe(2); // Premium, Luxury
});

// ----------------------------------------------------------------------
// groupByDateRanges - Date range buckets
// ----------------------------------------------------------------------

it('groups by date ranges', function () {
    Product::insert([
        ['name' => 'A', 'created_at' => '2023-01-15T00:00:00Z'],
        ['name' => 'B', 'created_at' => '2023-06-15T00:00:00Z'],
        ['name' => 'C', 'created_at' => '2024-01-15T00:00:00Z'],
    ]);

    $results = Product::groupByDateRanges('created_at', [
        ['to' => '2023-06-01'],
        ['from' => '2023-06-01', 'to' => '2024-01-01'],
        ['from' => '2024-01-01'],
    ])->get()->toArray();
    // Todo test actual counts

    expect($results)->toBeArray();
});

// ----------------------------------------------------------------------
// Custom bucket aggregation
// ----------------------------------------------------------------------

it('creates custom bucket aggregation', function () {
    Product::insert([
        ['name' => 'A', 'category' => 'tools', 'price' => 100],
        ['name' => 'B', 'category' => 'tools', 'price' => 200],
        ['name' => 'C', 'category' => 'electronics', 'price' => 300],
    ]);

    $results = Product::bucket('categories', 'terms', ['field' => 'category'])
        ->getAggregationResults();

    expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($results)->toHaveCount(2);
});

it('creates nested bucket aggregation with sub-aggregations', function () {
    Product::insert([
        ['name' => 'A', 'category' => 'tools', 'price' => 100],
        ['name' => 'B', 'category' => 'tools', 'price' => 200],
        ['name' => 'C', 'category' => 'electronics', 'price' => 300],
    ]);

    $results = Product::bucket('categories', 'terms', ['field' => 'category'], function ($query) {
        $query->bucket('avg_price', 'avg', ['field' => 'price']);
    })->get()->toArray();
    // Todo test actual avg_price values
    expect($results)->toBeArray();
});

// ----------------------------------------------------------------------
// Aggregation Results
// ----------------------------------------------------------------------

it('gets processed aggregation results', function () {
    Product::insert([
        ['name' => 'A', 'category' => 'tools'],
        ['name' => 'B', 'category' => 'electronics'],
    ]);

    $results = Product::groupBy('category')->getAggregationResults();

    expect($results)->toBeInstanceOf(\PDPhilip\Elasticsearch\Eloquent\ElasticCollection::class);
});

it('gets raw aggregation results', function () {
    Product::insert([
        ['name' => 'A', 'category' => 'tools'],
        ['name' => 'B', 'category' => 'electronics'],
    ]);

    $results = Product::groupBy('category')->toBase()->getRawAggregationResults();

    expect($results)->toBeArray()
        ->and($results)->toHaveKey('group_by');
});

// ----------------------------------------------------------------------
// Combining aggregations with filters
// ----------------------------------------------------------------------

it('aggregates filtered results', function () {
    Product::insert([
        ['name' => 'A', 'category' => 'tools', 'price' => 100],
        ['name' => 'B', 'category' => 'tools', 'price' => 200],
        ['name' => 'C', 'category' => 'electronics', 'price' => 300],
    ]);

    $avgPrice = Product::where('category', 'tools')->avg('price');

    expect($avgPrice)->toBe(150.0);
});

it('computes stats on filtered results', function () {
    Product::insert([
        ['name' => 'A', 'category' => 'tools', 'price' => 100],
        ['name' => 'B', 'category' => 'tools', 'price' => 200],
        ['name' => 'C', 'category' => 'electronics', 'price' => 300],
    ]);

    $stats = Product::where('category', 'tools')->stats('price');

    expect($stats['count'])->toBe(2)
        ->and($stats['min'])->toBe(100.0)
        ->and($stats['max'])->toBe(200.0);
});

<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Tests\Models\Product;

beforeEach(function () {
    Product::executeSchema();
});

/*
|--------------------------------------------------------------------------
| DSL Output Tests
|--------------------------------------------------------------------------
|
| Tests for query inspection methods that output the underlying
| Elasticsearch DSL query structure without executing it.
|
*/

// ----------------------------------------------------------------------
// toDsl - Get compiled query without execution
// ----------------------------------------------------------------------

it('outputs DSL for simple where query', function () {
    $dsl = Product::where('status', 'active')->toDsl();

    expect($dsl)->toBeArray()
        ->and($dsl)->toHaveKey('body')
        ->and($dsl)->toHaveKey('index')
        ->and($dsl['index'])->toBe('products');
});

it('outputs DSL for complex query with multiple conditions', function () {
    $dsl = Product::where('category', 'tools')
        ->where('price', '>', 100)
        ->where('status', 'active')
        ->orderBy('price', 'desc')
        ->limit(10)
        ->toDsl();

    expect($dsl)->toBeArray()
        ->and($dsl['body'])->toHaveKey('query')
        ->and($dsl['body'])->toHaveKey('size')
        ->and($dsl['body']['size'])->toBe(10);
});

it('outputs DSL for search queries', function () {
    $dsl = Product::searchTerm('widget', ['name', 'description'])->toDsl();

    expect($dsl)->toBeArray()
        ->and($dsl['body'])->toHaveKey('query');
});

it('outputs DSL for aggregation queries', function () {
    $dsl = Product::groupBy('category')->toDsl();

    expect($dsl)->toBeArray()
        ->and($dsl['body'])->toHaveKey('aggs');
});

// ----------------------------------------------------------------------
// toCompiledQuery - Alias for toDsl
// ----------------------------------------------------------------------

it('outputs compiled query as array', function () {
    $compiled = Product::where('status', 'active')->toCompiledQuery();
    expect($compiled)->toBeArray()
        ->and($compiled)->toHaveKey('index')
        ->and($compiled)->toHaveKey('body');
});

// ----------------------------------------------------------------------
// Query structure inspection
// ----------------------------------------------------------------------

it('shows bool query structure with must clauses', function () {
    $dsl = Product::where('category', 'tools')
        ->where('status', 'active')
        ->toDsl();

    $query = $dsl['body']['query'];

    expect($query)->toHaveKey('bool')
        ->and($query['bool'])->toHaveKey('must');
});

it('shows bool query structure with filter clauses', function () {
    $dsl = Product::filterWhere('category', 'tools')
        ->filterWhere('status', 'active')
        ->toDsl();

    $query = $dsl['body']['query'];

    expect($query)->toHaveKey('bool')
        ->and($query['bool'])->toHaveKey('filter');
});

it('shows bool query with must_not clauses', function () {
    $dsl = Product::whereNot(function ($query) {
        $query->where('status', 'deleted');
    })->toDsl();

    $query = $dsl['body']['query'];

    expect($query)->toHaveKey('bool')
        ->and($query['bool'])->toHaveKey('must_not');
});

it('shows bool query with should clauses', function () {
    $dsl = Product::where('category', 'tools')
        ->orWhere('category', 'electronics')
        ->toDsl();

    $query = $dsl['body']['query'];

    expect($query)->toHaveKey('bool');
});

// ----------------------------------------------------------------------
// Sort structure
// ----------------------------------------------------------------------

it('shows sort structure in DSL', function () {
    $dsl = Product::orderBy('price', 'desc')
        ->orderBy('name.keyword', 'asc')
        ->toDsl();

    expect($dsl['body'])->toHaveKey('sort')
        ->and($dsl['body']['sort'])->toBeArray();
});

// ----------------------------------------------------------------------
// Pagination structure
// ----------------------------------------------------------------------

it('shows pagination structure with from and size', function () {
    $dsl = Product::skip(20)->take(10)->toDsl();

    expect($dsl['body']['from'])->toBe(20)
        ->and($dsl['body']['size'])->toBe(10);
});

// ----------------------------------------------------------------------
// Select/Source structure
// ----------------------------------------------------------------------

it('shows source selection in DSL', function () {
    $dsl = Product::select(['name', 'price'])->toDsl();
    expect($dsl)->toHaveKey('_source');
});

// ----------------------------------------------------------------------
// Range queries
// ----------------------------------------------------------------------

it('shows range query structure', function () {
    $dsl = Product::whereBetween('price', [100, 500])->toDsl();
    $query = $dsl['body']['query'];
    expect($query)->toHaveKey('range');
});

// ----------------------------------------------------------------------
// Term queries
// ----------------------------------------------------------------------

it('shows term query structure', function () {
    $dsl = Product::whereTerm('status', 'active')->toDsl();

    expect($dsl['body']['query'])->toBeArray();
});

// ----------------------------------------------------------------------
// Match queries
// ----------------------------------------------------------------------

it('shows match query structure', function () {
    $dsl = Product::whereMatch('description', 'high quality')->toDsl();

    expect($dsl['body']['query'])->toBeArray();
});

// ----------------------------------------------------------------------
// Multi-match queries
// ----------------------------------------------------------------------

it('shows multi-match query structure', function () {
    $dsl = Product::searchTerm('widget', ['name', 'description'])->toDsl();

    expect($dsl['body']['query'])->toBeArray();
});

// ----------------------------------------------------------------------
// Nested queries
// ----------------------------------------------------------------------

it('shows nested query structure', function () {
    $dsl = Product::whereNestedObject('variants', function ($query) {
        $query->where('variants.color', 'red');
    })->toDsl();
    expect($dsl['body']['query'])->toHaveKey('nested');
});

// ----------------------------------------------------------------------
// Geo queries
// ----------------------------------------------------------------------

it('shows geo distance query structure', function () {
    $dsl = Product::whereGeoDistance('location', '10km', ['lat' => 40.7128, 'lon' => -74.0060])->toDsl();

    expect($dsl['body']['query'])->toBeArray();
});

// ----------------------------------------------------------------------
// Aggregation DSL
// ----------------------------------------------------------------------

it('shows bucket aggregation DSL', function () {
    $dsl = Product::bucket('categories', 'terms', ['field' => 'category'])->toDsl();

    expect($dsl['body'])->toHaveKey('aggs')
        ->and($dsl['body']['aggs'])->toHaveKey('categories');
});

it('shows metric aggregation DSL via query builder', function () {
    // Use the toBase() to access the query builder directly
    $query = Product::query()->toBase();
    $query->metricsAggregations[] = [
        'key' => 'price',
        'args' => 'price',
        'type' => 'avg',
        'options' => [],
    ];
    $compiled = $query->toDsl();

    expect($compiled['body'])->toHaveKey('aggs');
});

// ----------------------------------------------------------------------
// Combined query with search and filters
// ----------------------------------------------------------------------

it('shows complex query combining search, filter, and aggregations', function () {
    $dsl = Product::searchTerm('widget', ['name'])
        ->filterWhere('status', 'active')
        ->where('price', '>', 100)
        ->groupBy('category')
        ->orderBy('_score', 'desc')
        ->limit(20)
        ->toDsl();

    expect($dsl)->toBeArray()
        ->and($dsl['body'])->toHaveKey('query')
        ->and($dsl['body'])->toHaveKey('aggs')
        ->and($dsl['body'])->toHaveKey('sort')
        ->and($dsl['body'])->toHaveKey('size');
});

// ----------------------------------------------------------------------
// Script queries
// ----------------------------------------------------------------------

it('shows script query structure', function () {
    $dsl = Product::whereScript("doc['price'].value > 100")->toDsl();

    expect($dsl['body']['query'])->toBeArray();
});

// ----------------------------------------------------------------------
// Query with options
// ----------------------------------------------------------------------

it('includes request options in compiled query', function () {
    $dsl = Product::query()->toDsl();

    expect($dsl)->toHaveKey('index');
});

// ----------------------------------------------------------------------
// Debugging use cases
// ----------------------------------------------------------------------

it('can be used for debugging complex queries', function () {
    $query = Product::where('category', 'tools')
        ->searchTerm('professional widget', ['name', 'description'])
        ->filterWhere('price', '>=', 100)
        ->filterWhereIn('status', ['active', 'pending'])
        ->orderBy('_score', 'desc')
        ->orderBy('created_at', 'desc')
        ->limit(25);

    $dsl = $query->toDsl();

    // Can inspect the DSL for debugging
    expect($dsl)->toBeArray()
        ->and($dsl['index'])->toBe('products')
        ->and($dsl['body']['size'])->toBe(25);

    // Can convert to JSON for logging/debugging
    $json = json_encode($dsl, JSON_PRETTY_PRINT);
    expect($json)->toBeString();
});

it('outputs consistent DSL when called multiple times', function () {
    $query = Product::where('status', 'active')->orderBy('name.keyword');

    $dsl1 = $query->toDsl();
    $dsl2 = $query->toDsl();

    expect($dsl1)->toBe($dsl2);
});

it('shows correct DSL after modifying the query', function () {
    $query = Product::where('status', 'active');

    $dsl1 = $query->toDsl();

    $query->where('category', 'tools');
    $dsl2 = $query->toDsl();

    expect($dsl1)->not->toBe($dsl2);
});

it('outputs DSL when starting any query with dslQuery()', function () {
    $dsl = Product::dslQuery()->where('category', 'tools')
        ->searchTerm('professional widget', ['name', 'description'])
        ->filterWhere('price', '>=', 100)
        ->filterWhereIn('status', ['active', 'pending'])
        ->orderBy('_score', 'desc')
        ->orderBy('created_at', 'desc')
        ->limit(25)
        ->get();

    expect($dsl)->toBeArray()
        ->and($dsl['index'])->toBe('products')
        ->and($dsl['body']['size'])->toBe(25);
});

// ----------------------------------------------------------------------
// dslQuery() with different executors
// ----------------------------------------------------------------------

it('outputs DSL via dslQuery count() instead of executing', function () {
    $dsl = Product::dslQuery()->where('status', 'active')->count();

    expect($dsl)->toBeArray()
        ->and($dsl)->toHaveKey('index')
        ->and($dsl['index'])->toBe('products')
        ->and($dsl)->toHaveKey('body')
        ->and($dsl['body'])->toHaveKey('query');
});

it('outputs DSL via dslQuery min() instead of executing', function () {
    $dsl = Product::dslQuery()->where('status', 'active')->min('price');

    expect($dsl)->toBeArray()
        ->and($dsl)->toHaveKey('index')
        ->and($dsl['index'])->toBe('products')
        ->and($dsl['body'])->toHaveKey('aggs')
        ->and($dsl['body']['aggs'])->toHaveKey('price')
        ->and($dsl['body']['aggs']['price'])->toHaveKey('min');
});

it('outputs DSL via dslQuery max() instead of executing', function () {
    $dsl = Product::dslQuery()->where('status', 'active')->max('price');

    expect($dsl)->toBeArray()
        ->and($dsl)->toHaveKey('index')
        ->and($dsl['index'])->toBe('products')
        ->and($dsl['body'])->toHaveKey('aggs')
        ->and($dsl['body']['aggs'])->toHaveKey('price')
        ->and($dsl['body']['aggs']['price'])->toHaveKey('max');
});

it('outputs DSL via dslQuery sum() instead of executing', function () {
    $dsl = Product::dslQuery()->where('category', 'tools')->sum('price');

    expect($dsl)->toBeArray()
        ->and($dsl)->toHaveKey('index')
        ->and($dsl['index'])->toBe('products')
        ->and($dsl['body'])->toHaveKey('aggs')
        ->and($dsl['body']['aggs'])->toHaveKey('price')
        ->and($dsl['body']['aggs']['price'])->toHaveKey('sum');
});

it('outputs DSL via dslQuery avg() instead of executing', function () {
    $dsl = Product::dslQuery()->where('category', 'tools')->avg('price');

    expect($dsl)->toBeArray()
        ->and($dsl)->toHaveKey('index')
        ->and($dsl['index'])->toBe('products')
        ->and($dsl['body'])->toHaveKey('aggs')
        ->and($dsl['body']['aggs'])->toHaveKey('price')
        ->and($dsl['body']['aggs']['price'])->toHaveKey('avg');
});

it('outputs DSL via dslQuery first() using get() with limit', function () {
    // first() calls get() internally which returns DSL when asDsl=true
    $dsl = Product::dslQuery()->where('status', 'active')->orderBy('price')->limit(1)->get();

    expect($dsl)->toBeArray()
        ->and($dsl)->toHaveKey('index')
        ->and($dsl['index'])->toBe('products')
        ->and($dsl['body'])->toHaveKey('query')
        ->and($dsl['body'])->toHaveKey('sort')
        ->and($dsl['body']['size'])->toBe(1);
});

it('outputs DSL via dslQuery stats() instead of executing', function () {
    $dsl = Product::dslQuery()->where('status', 'active')->stats('price');

    expect($dsl)->toBeArray()
        ->and($dsl)->toHaveKey('index')
        ->and($dsl['index'])->toBe('products')
        ->and($dsl['body'])->toHaveKey('aggs')
        ->and($dsl['body']['aggs'])->toHaveKey('price')
        ->and($dsl['body']['aggs']['price'])->toHaveKey('stats');
});

it('outputs DSL via dslQuery agg() with multiple metrics instead of executing', function () {
    $dsl = Product::dslQuery()->where('category', 'tools')->agg(['min', 'max', 'avg'], 'price');

    expect($dsl)->toBeArray()
        ->and($dsl)->toHaveKey('index')
        ->and($dsl['index'])->toBe('products')
        ->and($dsl['body'])->toHaveKey('aggs')
        ->and($dsl['body']['aggs'])->toHaveKey('min_price')
        ->and($dsl['body']['aggs'])->toHaveKey('max_price')
        ->and($dsl['body']['aggs'])->toHaveKey('avg_price');
});

// ----------------------------------------------------------------------
// Date-based where DSL
// ----------------------------------------------------------------------

it('outputs DSL for whereYear with script query', function () {
    $dsl = Product::whereYear('created_at', '2024')->toDsl();

    expect($dsl)->toBeArray()
        ->and($dsl['body'])->toHaveKey('query')
        ->and($dsl['body']['query'])->toHaveKey('script');

    // Script uses doc[field].value.year for date part extraction
    $script = $dsl['body']['query']['script']['script'];
    expect($script['source'])->toContain('doc')
        ->and($script['source'])->toContain('.year');
});

it('outputs DSL for whereTime with script query', function () {
    $dsl = Product::whereTime('created_at', '10:30:00')->toDsl();

    expect($dsl)->toBeArray()
        ->and($dsl['body'])->toHaveKey('query')
        ->and($dsl['body']['query'])->toHaveKey('script');

    // Script should use time-related properties (hour, minute, second)
    $script = $dsl['body']['query']['script']['script'];
    expect($script['source'])->toContain('doc')
        ->and($script['source'])->toContain('.hour')
        ->and($script['source'])->toContain('.minute')
        ->and($script['source'])->toContain('.second');
});

<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Tests\Models\Product;

beforeEach(function () {
    Product::executeSchema();
});

/*
|--------------------------------------------------------------------------
| Filter Context Queries
|--------------------------------------------------------------------------
|
| Filter queries in Elasticsearch do not contribute to document scoring.
| They are cached and more efficient for filtering without relevance scoring.
| Pass 'postFilter' as the final argument for faceted search filtering.
|
*/

// ----------------------------------------------------------------------
// filterWhere - Basic filtering
// ----------------------------------------------------------------------

it('filters with filterWhere without affecting score', function () {
    Product::insert([
        ['name' => 'Widget', 'category' => 'tools', 'price' => 100],
        ['name' => 'Gadget', 'category' => 'tools', 'price' => 200],
        ['name' => 'Device', 'category' => 'electronics', 'price' => 300],
    ]);

    $results = Product::filterWhere('category', 'tools')->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('category')->unique()->first())->toBe('tools');
});

it('combines filterWhere with search queries', function () {
    Product::insert([
        ['name' => 'Red Widget', 'category' => 'tools', 'price' => 100],
        ['name' => 'Blue Widget', 'category' => 'electronics', 'price' => 200],
        ['name' => 'Red Gadget', 'category' => 'tools', 'price' => 150],
    ]);

    // Search scores "widget" documents, filter on category
    $results = Product::searchTerm('widget', ['name'])
        ->filterWhere('category', 'tools')
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Red Widget');
});

it('uses filterWhere with comparison operators', function () {
    Product::insert([
        ['name' => 'Cheap', 'price' => 50],
        ['name' => 'Medium', 'price' => 150],
        ['name' => 'Expensive', 'price' => 500],
    ]);

    $results = Product::filterWhere('price', '>=', 150)->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('name'))->toContain('Medium', 'Expensive');
});

// ----------------------------------------------------------------------
// filterWhereIn - Filter by multiple values
// ----------------------------------------------------------------------

it('filters by multiple values with filterWhereIn', function () {
    Product::insert([
        ['name' => 'A', 'status' => 'active'],
        ['name' => 'B', 'status' => 'pending'],
        ['name' => 'C', 'status' => 'inactive'],
        ['name' => 'D', 'status' => 'active'],
    ]);

    $results = Product::filterWhereIn('status', ['active', 'pending'])->get();

    expect($results)->toHaveCount(3)
        ->and($results->pluck('status'))->toContain('active', 'pending');
});

// ----------------------------------------------------------------------
// filterWhereNotIn - Exclude multiple values
// ----------------------------------------------------------------------

it('excludes multiple values with filterWhereNotIn', function () {
    Product::insert([
        ['name' => 'A', 'status' => 'active'],
        ['name' => 'B', 'status' => 'pending'],
        ['name' => 'C', 'status' => 'inactive'],
    ]);

    $results = Product::filterWhereNotIn('status', ['active', 'pending'])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->status)->toBe('inactive');
});

// ----------------------------------------------------------------------
// filterWhereBetween - Range filtering
// ----------------------------------------------------------------------

it('filters by range with filterWhereBetween', function () {
    Product::insert([
        ['name' => 'A', 'price' => 50],
        ['name' => 'B', 'price' => 100],
        ['name' => 'C', 'price' => 200],
        ['name' => 'D', 'price' => 500],
    ]);

    $results = Product::filterWhereBetween('price', [100, 250])->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('name')->sort()->values()->toArray())->toBe(['B', 'C']);
});

// ----------------------------------------------------------------------
// filterWhereNull / filterWhereNotNull
// ----------------------------------------------------------------------

it('filters null values with filterWhereNull', function () {
    Product::insert([
        ['name' => 'A', 'description' => 'Has description'],
        ['name' => 'B', 'description' => null],
        ['name' => 'C'],  // No description field
    ]);

    $results = Product::filterWhereNull('description')->get();

    expect($results)->toHaveCount(2);
});

it('filters non-null values with filterWhereNotNull', function () {
    Product::insert([
        ['name' => 'A', 'description' => 'Has description'],
        ['name' => 'B', 'description' => null],
        ['name' => 'C'],  // No description field
    ]);

    $results = Product::filterWhereNotNull('description')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('A');
});

// ----------------------------------------------------------------------
// filterWhereDate - Date filtering
// ----------------------------------------------------------------------

it('filters by date with filterWhereDate', function () {
    Product::insert([
        ['name' => 'A', 'created_at' => '2024-01-15T00:00:00Z'],
        ['name' => 'B', 'created_at' => '2024-01-15T12:30:00Z'],
        ['name' => 'C', 'created_at' => '2024-02-01T00:00:00Z'],
    ]);

    $results = Product::filterWhereDate('created_at', '2024-01-15')->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('name')->sort()->values()->toArray())->toBe(['A', 'B']);
});

// ----------------------------------------------------------------------
// filterWhereTerm - Exact term filtering
// ----------------------------------------------------------------------

it('filters exact term with filterWhereTerm', function () {
    Product::insert([
        ['name' => 'Widget Pro', 'sku' => 'WID-001'],
        ['name' => 'Widget Basic', 'sku' => 'WID-002'],
        ['name' => 'Gadget', 'sku' => 'GAD-001'],
    ]);

    $results = Product::filterWhereTerm('sku', 'WID-001')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Widget Pro');
});

// ----------------------------------------------------------------------
// filterWhereMatch - Text matching in filter context
// ----------------------------------------------------------------------

it('filters with text matching', function () {
    Product::insert([
        ['name' => 'Professional Widget', 'description' => 'High quality widget for professionals'],
        ['name' => 'Basic Widget', 'description' => 'Simple widget for beginners'],
        ['name' => 'Premium Gadget', 'description' => 'High quality gadget'],
    ]);

    $results = Product::filterWhereMatch('description', 'high quality')->get();

    expect($results)->toHaveCount(2);
});

// ----------------------------------------------------------------------
// filterWherePhrase - Exact phrase filtering
// ----------------------------------------------------------------------

it('filters by exact phrase with filterWherePhrase', function () {
    Product::insert([
        ['name' => 'A', 'description' => 'A high quality product'],
        ['name' => 'B', 'description' => 'Quality is high in this product'],
        ['name' => 'C', 'description' => 'High and quality product'],
    ]);

    $results = Product::filterWherePhrase('description', 'high quality')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('A');
});

// ----------------------------------------------------------------------
// filterWhereFuzzy - Fuzzy matching in filter context
// ----------------------------------------------------------------------

it('filters with fuzzy matching for typo tolerance', function () {
    Product::insert([
        ['name' => 'Widget'],
        ['name' => 'Gadget'],
        ['name' => 'Budget'],
    ]);

    // "Widgit" with typo should match "Widget"
    $results = Product::filterWhereFuzzy('name', 'Widgit')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Widget');
});

// ----------------------------------------------------------------------
// filterWhereRegex - Regular expression filtering
// ----------------------------------------------------------------------

it('filters with regex pattern', function () {
    Product::insert([
        ['name' => 'Widget', 'sku' => 'WID-001'],
        ['name' => 'Widget Pro', 'sku' => 'WID-002'],
        ['name' => 'Gadget', 'sku' => 'GAD-001'],
    ]);

    $results = Product::filterWhereRegex('sku', 'WID-.*')->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('sku'))->toContain('WID-001', 'WID-002');
});

// ----------------------------------------------------------------------
// filterWhereGeoDistance - Geographic filtering
// ----------------------------------------------------------------------

it('filters by geographic distance', function () {
    Product::insert([
        ['name' => 'Store A', 'location' => ['lat' => 40.7128, 'lon' => -74.0060]], // NYC
        ['name' => 'Store B', 'location' => ['lat' => 34.0522, 'lon' => -118.2437]], // LA
        ['name' => 'Store C', 'location' => ['lat' => 41.8781, 'lon' => -87.6298]], // Chicago
    ]);

    // Find stores within 500km of Chicago
    $results = Product::filterWhereGeoDistance('location', '500km', ['lat' => 41.8781, 'lon' => -87.6298])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Store C');
});

// ----------------------------------------------------------------------
// filterWhereGeoBox - Geographic bounding box filtering
// ----------------------------------------------------------------------

it('filters by geographic bounding box', function () {
    Product::insert([
        ['name' => 'NYC', 'location' => ['lat' => 40.7128, 'lon' => -74.0060]],
        ['name' => 'LA', 'location' => ['lat' => 34.0522, 'lon' => -118.2437]],
        ['name' => 'Chicago', 'location' => ['lat' => 41.8781, 'lon' => -87.6298]],
    ]);

    // Bounding box roughly around Eastern US (NYC and Chicago)
    $topLeft = ['lat' => 45.0, 'lon' => -90.0];
    $bottomRight = ['lat' => 35.0, 'lon' => -70.0];

    $results = Product::filterWhereGeoBox('location', $topLeft, $bottomRight)->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('name'))->toContain('NYC', 'Chicago');
});

// ----------------------------------------------------------------------
// filterWhereNestedObject - Nested object filtering
// ----------------------------------------------------------------------

it('filters within nested objects', function () {
    Product::insert([
        [
            'name' => 'Product A',
            'variants' => [
                ['color' => 'red', 'size' => 'M'],
                ['color' => 'blue', 'size' => 'L'],
            ],
        ],
        [
            'name' => 'Product B',
            'variants' => [
                ['color' => 'green', 'size' => 'S'],
            ],
        ],
    ]);

    $results = Product::filterWhereNestedObject('variants', function ($query) {
        $query->where('variants.color', 'red');
    })->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Product A');
});

// ----------------------------------------------------------------------
// Combining multiple filter clauses
// ----------------------------------------------------------------------

it('combines multiple filter clauses', function () {
    Product::insert([
        ['name' => 'A', 'category' => 'tools', 'price' => 100, 'status' => 'active'],
        ['name' => 'B', 'category' => 'tools', 'price' => 200, 'status' => 'active'],
        ['name' => 'C', 'category' => 'electronics', 'price' => 150, 'status' => 'active'],
        ['name' => 'D', 'category' => 'tools', 'price' => 300, 'status' => 'inactive'],
    ]);

    $results = Product::filterWhere('category', 'tools')
        ->filterWhere('status', 'active')
        ->filterWhereBetween('price', [50, 250])
        ->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('name')->sort()->values()->toArray())->toBe(['A', 'B']);
});

// ----------------------------------------------------------------------
// Post-filter for faceted search
// ----------------------------------------------------------------------

it('uses post-filter for faceted search', function () {
    Product::insert([
        ['name' => 'Widget Red', 'category' => 'tools', 'color' => 'red'],
        ['name' => 'Widget Blue', 'category' => 'tools', 'color' => 'blue'],
        ['name' => 'Gadget Red', 'category' => 'electronics', 'color' => 'red'],
    ]);

    // Post-filter applies after aggregations, useful for faceted navigation
    $results = Product::searchTerm('widget', ['name'])
        ->filterWhere('color', '=', 'red', 'postFilter')
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Widget Red');
});

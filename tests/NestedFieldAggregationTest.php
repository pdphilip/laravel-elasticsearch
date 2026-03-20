<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Tests\Models\Product;

beforeEach(function () {
    Product::executeSchema();
});

/*
|--------------------------------------------------------------------------
| Nested Field Aggregation Tests
|--------------------------------------------------------------------------
|
| ES requires aggregations on nested fields to be wrapped in a nested
| aggregation context. These tests verify that distinct(), bulkDistinct(),
| and groupBy() auto-detect nested mappings and generate the correct DSL.
|
| Product model has: nested('variants') → keyword('sku'), keyword('color')
|
*/

// ----------------------------------------------------------------------
// Distinct on nested sub-fields
// ----------------------------------------------------------------------

it('aggregates distinct values on a nested sub-field', function () {
    Product::insert([
        ['name' => 'Shirt', 'variants' => [['color' => 'red', 'sku' => 'SH-R'], ['color' => 'blue', 'sku' => 'SH-B']]],
        ['name' => 'Pants', 'variants' => [['color' => 'red', 'sku' => 'PA-R'], ['color' => 'green', 'sku' => 'PA-G']]],
        ['name' => 'Hat', 'variants' => [['color' => 'blue', 'sku' => 'HA-B']]],
    ]);

    $results = Product::distinct('variants.color');

    $colors = $results->map(fn ($r) => $r['variants.color'])->sort()->values()->all();
    expect($results)->toHaveCount(3)
        ->and($colors)->toBe(['blue', 'green', 'red']);
});

it('aggregates distinct values on a nested sub-field with count', function () {
    Product::insert([
        ['name' => 'Shirt', 'variants' => [['color' => 'red', 'sku' => 'SH-R'], ['color' => 'blue', 'sku' => 'SH-B']]],
        ['name' => 'Pants', 'variants' => [['color' => 'red', 'sku' => 'PA-R'], ['color' => 'green', 'sku' => 'PA-G']]],
        ['name' => 'Hat', 'variants' => [['color' => 'blue', 'sku' => 'HA-B']]],
    ]);

    $results = Product::distinct('variants.color', true);

    expect($results)->toHaveCount(3);

    $red = $results->first(fn ($r) => $r['variants.color'] === 'red');
    $blue = $results->first(fn ($r) => $r['variants.color'] === 'blue');
    $green = $results->first(fn ($r) => $r['variants.color'] === 'green');

    expect($red['variants.color_count'])->toBe(2)
        ->and($blue['variants.color_count'])->toBe(2)
        ->and($green['variants.color_count'])->toBe(1);
});

it('aggregates distinct on multiple nested sub-fields sharing the same path', function () {
    Product::insert([
        ['name' => 'Shirt', 'variants' => [['color' => 'red', 'sku' => 'SH-R'], ['color' => 'blue', 'sku' => 'SH-B']]],
        ['name' => 'Pants', 'variants' => [['color' => 'red', 'sku' => 'PA-R']]],
    ]);

    $results = Product::distinct(['variants.color', 'variants.sku'], true);

    expect($results)->toHaveCount(3);

    $shirtRed = $results->first(fn ($r) => $r['variants.sku'] === 'SH-R');
    expect($shirtRed['variants.color'])->toBe('red')
        ->and($shirtRed['variants.sku_count'])->toBe(1);
});

// ----------------------------------------------------------------------
// Bulk distinct on nested sub-fields
// ----------------------------------------------------------------------

it('aggregates bulk distinct on nested sub-fields', function () {
    Product::insert([
        ['name' => 'Shirt', 'variants' => [['color' => 'red', 'sku' => 'SH-R'], ['color' => 'blue', 'sku' => 'SH-B']]],
        ['name' => 'Pants', 'variants' => [['color' => 'red', 'sku' => 'PA-R'], ['color' => 'green', 'sku' => 'PA-G']]],
    ]);

    $results = Product::bulkDistinct(['variants.color', 'variants.sku'], true);

    $colors = $results->filter(fn ($r) => isset($r['variants.color']));
    $skus = $results->filter(fn ($r) => isset($r['variants.sku']));

    expect($colors)->toHaveCount(3)
        ->and($skus)->toHaveCount(4);
});

// ----------------------------------------------------------------------
// GroupBy on nested sub-fields
// ----------------------------------------------------------------------

it('groups by a nested sub-field', function () {
    Product::insert([
        ['name' => 'Shirt', 'variants' => [['color' => 'red', 'sku' => 'SH-R'], ['color' => 'blue', 'sku' => 'SH-B']]],
        ['name' => 'Pants', 'variants' => [['color' => 'red', 'sku' => 'PA-R'], ['color' => 'green', 'sku' => 'PA-G']]],
        ['name' => 'Hat', 'variants' => [['color' => 'blue', 'sku' => 'HA-B']]],
    ]);

    $results = Product::groupBy('variants.color')->get();

    expect($results)->toHaveCount(3);

    $colors = $results->map(fn ($r) => $r['variants.color'])->sort()->values()->all();
    expect($colors)->toBe(['blue', 'green', 'red']);
});

// ----------------------------------------------------------------------
// Distinct with whereNestedObject filter
// ----------------------------------------------------------------------

it('aggregates distinct on nested field filtered by whereNestedObject', function () {
    Product::insert([
        [
            'name' => 'Shirt',
            'variants' => [
                ['color' => 'red', 'sku' => 'SH-R'],
                ['color' => 'blue', 'sku' => 'SH-B'],
            ],
        ],
        [
            'name' => 'Pants',
            'variants' => [
                ['color' => 'red', 'sku' => 'PA-R'],
                ['color' => 'green', 'sku' => 'PA-G'],
            ],
        ],
        [
            'name' => 'Hat',
            'variants' => [
                ['color' => 'blue', 'sku' => 'HA-B'],
            ],
        ],
    ]);

    // Filter to red variants inside the nested agg, then get distinct SKUs
    $results = Product::whereNestedObject('variants', function ($q) {
        $q->where('color', 'red');
    })->distinct('variants.sku', true);

    $skus = $results->map(fn ($r) => $r['variants.sku'])->sort()->values()->all();
    expect($skus)->toBe(['PA-R', 'SH-R']);
});

// ----------------------------------------------------------------------
// Non-nested fields unaffected (regression guard)
// ----------------------------------------------------------------------

it('does not wrap non-nested fields in nested aggregation', function () {
    Product::insert([
        ['name' => 'Widget', 'category' => 'tools', 'price' => 100],
        ['name' => 'Gadget', 'category' => 'tools', 'price' => 200],
        ['name' => 'Gizmo', 'category' => 'electronics', 'price' => 150],
    ]);

    $results = Product::distinct('category', true);

    expect($results)->toHaveCount(2);

    $tools = $results->first(fn ($r) => $r['category'] === 'tools');
    expect($tools['category_count'])->toBe(2);
});

it('groups by non-nested field without nested wrapping', function () {
    Product::insert([
        ['name' => 'Widget', 'category' => 'tools', 'price' => 100],
        ['name' => 'Gadget', 'category' => 'tools', 'price' => 200],
        ['name' => 'Gizmo', 'category' => 'electronics', 'price' => 150],
    ]);

    $results = Product::groupBy('category')->get();

    expect($results)->toHaveCount(2);

    $categories = $results->pluck('category')->sort()->values()->all();
    expect($categories)->toBe(['electronics', 'tools']);
});

// ----------------------------------------------------------------------
// DSL output verification
// ----------------------------------------------------------------------

it('generates nested aggregation wrapper in DSL for nested distinct', function () {
    // Set distinct mode directly on the query builder
    $query = Product::where('status', 'active')->getQuery();
    $query->columns = ['variants.color'];
    $query->distinct = true;
    $dsl = $query->toDsl();

    expect($dsl['body']['aggs'])->toHaveKey('nested_variants')
        ->and($dsl['body']['aggs']['nested_variants']['nested']['path'])->toBe('variants')
        ->and($dsl['body']['aggs']['nested_variants']['aggs'])->toHaveKey('by_variants.color');
});

it('generates nested + filter wrapper in DSL when whereNestedObject is present', function () {
    $query = Product::whereNestedObject('variants', function ($q) {
        $q->where('color', 'red');
    })->getQuery();
    $query->columns = ['variants.sku'];
    $query->distinct = true;
    $dsl = $query->toDsl();

    $nestedAgg = $dsl['body']['aggs']['nested_variants'];
    expect($nestedAgg['nested']['path'])->toBe('variants')
        ->and($nestedAgg['aggs'])->toHaveKey('filtered')
        ->and($nestedAgg['aggs']['filtered'])->toHaveKey('filter')
        ->and($nestedAgg['aggs']['filtered']['aggs'])->toHaveKey('by_variants.sku');
});

it('does not generate nested wrapper for non-nested fields in DSL', function () {
    $query = Product::where('status', 'active')->getQuery();
    $query->columns = ['category'];
    $query->distinct = true;
    $dsl = $query->toDsl();

    expect($dsl['body']['aggs'])->toHaveKey('by_category')
        ->and($dsl['body']['aggs'])->not->toHaveKey('nested_variants');
});

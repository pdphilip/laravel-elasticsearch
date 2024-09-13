<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Factories\Sequence;
use Workbench\App\Models\Product;

test('retrieve distinct product statuses', function () {
    Product::factory()->state(['status' => 1])->count(3)->create();
    Product::factory()->state(['status' => 2])->count(2)->create();
    $statuses = Product::select('status')->distinct()->get();
    expect($statuses)->toHaveCount(2);
});

test('group products by status', function () {
    Product::factory()->state(['status' => 1])->count(3)->create();
    Product::factory()->state(['status' => 2])->count(2)->create();
    $grouped = Product::groupBy('status')->get();
    expect($grouped)->toBeCollection()
        ->and($grouped)->toHaveCount(2);
});

test('retrieve distinct products with multiple fields', function () {
    Product::factory()->state(['status' => 1, 'color' => 'blue'])->create();
    Product::factory()->state(['status' => 1, 'color' => 'red'])->create();
    $products = Product::distinct()->get(['status', 'color']);
    expect($products)->toHaveCount(2);
});

test('order products by the count of distinct status', function () {
    Product::factory()->state(['status' => 1])->count(5)->create();
    Product::factory()->state(['status' => 2])->count(2)->create();
    $products = Product::select('status')->distinct()->orderBy('status_count')->get();
    expect($products->first()->status)->toEqual(1); // Assuming it orders with the least first
});

test('get distinct statuses with their counts', function () {
    Product::factory()->state(['status' => 1])->count(5)->create();
    Product::factory()->state(['status' => 2])->count(3)->create();
    $statuses = Product::select('status')->distinct(true)->orderByDesc('status_count')->get();

    expect($statuses->first()->status_count)->toEqual(5);
});

test('Count', function () {
    $products = Product::factory(10)->make();
    Product::insert($products->toArray());
    expect(Product::count())->toBe(10);
});

test('Max', function () {
    $products = Product::factory(10)
        ->state(new Sequence(
            ['price' => 10.0],
            ['price' => 120.0],
        ))->make();
    Product::insert($products->toArray());
    expect(Product::max('price'))->toBe(120.0);
});

test('Min', function () {
    $products = Product::factory(10)
        ->state(new Sequence(
            ['price' => 10.0],
            ['price' => 120.0],
        ))->make();
    Product::insert($products->toArray());
    expect(Product::min('price'))->toBe(10.0);
});

test('Avg', function () {
    $products = Product::factory(10)
        ->state(new Sequence(
            ['price' => 10.0],
            ['price' => 120.0],
        ))->make();
    Product::insert($products->toArray());
    expect(Product::avg('price'))->toBe(65.0);
});

test('Sum', function () {
    $products = Product::factory(10)
        ->state(['price' => 10.0])->make();
    Product::insert($products->toArray());
    expect(Product::sum('price'))->toBe(100.0);
});

test('Multiple fields at once', function () {
    $products = Product::factory(10)
        ->state(new Sequence(
            ['price' => 10.0, 'discount_amount' => 5.0],
            ['price' => 120.0, 'discount_amount' => 7.0],
        ))->make();
    Product::insert($products->toArray());

    $max = Product::max(['price', 'discount_amount']);

    expect($max)->toBeArray()
        ->and($max['max_price'])->toBe(120.0)
        ->and($max['max_discount_amount'])->toBe(7.0);
});

test('Matrix', function () {
    $products = Product::factory(100)
        ->state(new Sequence(
            ['color' => 'red'],
            ['color' => 'green'],
            ['color' => 'blue'],
            ['color' => 'yellow'],
        ))->make();
    Product::insert($products->toArray());

    $matrix = Product::whereNotIn('color', ['red', 'green'])->matrix('price');

    expect($matrix)->toBeArray()
        ->and($matrix['fields'])->toBeArray();
});

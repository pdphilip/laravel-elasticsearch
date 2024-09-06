<?php

declare(strict_types=1);

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

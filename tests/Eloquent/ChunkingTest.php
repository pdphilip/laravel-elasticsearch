<?php

declare(strict_types=1);

use Workbench\App\Models\Product;

test('process large dataset using basic chunking', function () {
    $products = Product::factory(100)->state(['price' => 50])->make();
    Product::insert($products->toArray());
    sleep(3);

    Product::chunk(10, function ($products) {
        foreach ($products as $product) {
            $product->price *= 1.1;
            $product->saveWithoutRefresh();
        }
    });
    Product::waitForPendingTasks();

    $updatedProduct = Product::first();
    expect($updatedProduct->price)->toBeGreaterThan(50);
});

test('process large dataset using basic chunking with extended keepAlive', function () {
    $products = Product::factory(100)->state(['price' => 50])->make();
    Product::insert($products->toArray());

    Product::chunk(1000, function ($products) {
        foreach ($products as $product) {
            $product->price *= 1.1;
            $product->saveWithoutRefresh();
        }
    }, '20m'); // Using an extended keepAlive period
    Product::waitForPendingTasks();

    $updatedProduct = Product::first();
    expect($updatedProduct->price)->toBeGreaterThan(50);
});

test('chunk by ID on a specific column with custom keepAlive', function () {
    $products = Product::factory(100)->state(['price' => 50])->make();
    Product::insert($products->toArray());

    // Assuming 'product_id' is a unique identifier in the dataset
    Product::chunkById(1000, function ($products) {
        foreach ($products as $product) {
            $product->price *= 1.1;
            $product->saveWithoutRefresh();
        }
    }, 'product_id.keyword', null, '5m');
    sleep(3);

    $updatedProduct = Product::first();
    expect($updatedProduct->price)->toBeGreaterThan(50);
});

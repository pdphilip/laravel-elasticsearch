<?php

declare(strict_types=1);

use Workbench\App\Models\Product;

test('process large dataset using basic chunking', function () {
    $products = Product::factory(100)->state(['price' => 50])->make();
    Product::insert($products->toArray());
    Product::refreshIndex();

    Product::chunk(10, function ($products) {
        foreach ($products as $product) {
            $product->price *= 1.1;
            $product->saveWithoutRefresh();
        }
    });
    Product::refreshIndex();

    $updatedProduct = Product::first();
    expect($updatedProduct->price)->toBeGreaterThan(50);
});

test('process large dataset using basic chunking with extended keepAlive', function () {
    $products = Product::factory(100)->state(['price' => 50])->make();
    Product::insert($products->toArray());
    Product::refreshIndex();

    Product::chunk(1000, function ($products) {
        foreach ($products as $product) {
            $product->price *= 1.1;
            $product->saveWithoutRefresh();
        }
    }, '20m'); // Using an extended keepAlive period
    Product::refreshIndex();

    $updatedProduct = Product::first();
    expect($updatedProduct->price)->toBeGreaterThan(50);
});

test('chunk by ID on a specific column with custom keepAlive', function () {
    $products = Product::factory(100)->state(['price' => 50])->make();
    Product::insert($products->toArray());
    Product::refreshIndex();

    // Assuming 'product_id' is a unique identifier in the dataset
    Product::chunkById(1000, function ($products) {
        foreach ($products as $product) {
            $product->price *= 1.1;
            $product->saveWithoutRefresh();
        }
    }, 'product_id.keyword', null, '5m');
    Product::refreshIndex();

    $updatedProduct = Product::first();
    expect($updatedProduct->price)->toBeGreaterThan(50);
});

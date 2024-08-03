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

test('process large dataset using basic chunking', function () {
    Product::factory(100)->state(['price' => 50])->make()->each(function ($model) {
        $model->saveWithoutRefresh();
    });
    sleep(3);

    Product::chunk(10, function ($products) {
        foreach ($products as $product) {
            $product->price *= 1.1;
            $product->saveWithoutRefresh();
        }
    });
    sleep(3);

    $updatedProduct = Product::first();
    expect($updatedProduct->price)->toBeGreaterThan(50);
});

test('process large dataset using basic chunking with extended keepAlive', function () {
    Product::factory(100)->state(['price' => 50])->make()->each(function ($model) {
        $model->saveWithoutRefresh();
    });
    sleep(3);

    Product::chunk(1000, function ($products) {
        foreach ($products as $product) {
            $product->price *= 1.1;
            $product->saveWithoutRefresh();
        }
    }, '20m'); // Using an extended keepAlive period
    sleep(3);

    $updatedProduct = Product::first();
    expect($updatedProduct->price)->toBeGreaterThan(50);
});

test('chunk by ID on a specific column with custom keepAlive', function () {
    Product::factory(100)->state(['price' => 50])->make()->each(function ($model) {
        $model->saveWithoutRefresh();
    });
    sleep(3);

    // Assuming 'product_id' is a unique identifier in the dataset
    Product::chunkById(1000, function ($products) {
        foreach ($products as $product) {
            $product->price *= 1.1;
            $product->saveWithoutRefresh();
        }
    }, 'product_id', null, '5m');
    sleep(3);

    $updatedProduct = Product::first();
    expect($updatedProduct->price)->toBeGreaterThan(50);
});

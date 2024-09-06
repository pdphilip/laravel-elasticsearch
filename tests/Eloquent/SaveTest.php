<?php

declare(strict_types=1);

use Workbench\App\Models\Product;

test('save a new product with individual attributes', function () {
    $product = new Product;
    $product->name = 'New Product';
    $product->price = 199.99;
    $product->status = 1;
    $product->save();

    $found = Product::first();
    expect($found)->toBeInstanceOf(Product::class)
        ->and($found->name)->toEqual('New Product')
        ->and($found->price)->toEqual(199.99);
});

test('create a new product using mass assignment', function () {
    Product::create([
        'name' => 'Mass Assigned Product',
        'price' => 299.99,
        'status' => 1,
    ]);

    $found = Product::first();
    expect($found)->toBeInstanceOf(Product::class)
        ->and($found->name)->toEqual('Mass Assigned Product')
        ->and($found->price)->toEqual(299.99);
});

test('update a product attribute and save', function () {
    $product = Product::factory()->create(['status' => 1]);
    $product->status = 2;
    $product->save();

    $updated = Product::find($product->_id);
    expect($updated->status)->toEqual(2);
});

test('mass update products matching a condition', function () {
    Product::factory(5)->state(['status' => 1])->create();
    $updates = Product::where('status', 1)->update(['status' => 4]);

    $updatedCount = Product::where('status', 4)->count();
    expect($updates)->toEqual(5)
        ->and($updatedCount)->toEqual(5);
});

test('save product without waiting for index refresh', function () {
    $product = new Product;
    $product->name = 'Fast Save Product';
    $product->status = 1;
    $product->saveWithoutRefresh();

    // Note: Can't directly test the non-wait state, this would typically be tested with integration tests
    expect($product->wasRecentlyCreated)->toBeTrue();
});

test('first or create product based on unique attributes', function () {
    Product::factory()->create(['name' => 'Unique Product', 'status' => 1]);

    $product = Product::firstOrCreate(
        ['name' => 'Unique Product', 'status' => 1],
        ['price' => 99.99]
    );

    expect($product->wasRecentlyCreated)->toBeFalse()
        ->and($product->name)->toEqual('Unique Product');
});

test('first or create without refresh', function () {
    $product = Product::firstOrCreateWithoutRefresh(
        ['name' => 'Non-Refresh Product', 'status' => 1],
        ['price' => 109.99]
    );

    // Note: Similar to the fast save test, the non-refresh state is an integration aspect
    expect($product->wasRecentlyCreated)->toBeTrue()
        ->and($product->name)->toEqual('Non-Refresh Product');
});

test('validate saving a model with a unique constraint on name', function () {
    Product::create(['name' => 'Unique Gadget', 'price' => 100]);
    $duplicateProductAttempt = Product::firstOrCreate(
        ['name' => 'Unique Gadget'],
        ['price' => 200]
    );

    // Assert it didn't overwrite the existing product
    expect($duplicateProductAttempt->price)->toEqual(100)
      // Ensure no duplicate was created
        ->and(Product::count())->toEqual(1);

});

test('ensure save without refresh accurately models elastic behavior', function () {
    $product = new Product;
    $product->name = 'Delayed Visibility Product';
    $product->price = 150;
    $product->saveWithoutRefresh();

    $foundImmediately = Product::where('name', 'Delayed Visibility Product')->first();
    expect($foundImmediately)->toBeNull(); // Not immediately available
});

test('query using firstOrCreate to simulate inventory addition', function () {
    Product::factory()->create(['name' => 'Gadget', 'status' => 1]);

    $newOrExistingProduct = Product::firstOrCreate(
        ['name' => 'Gadget'],
        ['status' => 1, 'price' => 99.99]
    );

    expect($newOrExistingProduct->wasRecentlyCreated)->toBeFalse()
      // Price won't be 99.99 if it already existed
        ->and($newOrExistingProduct->price)->not()->toEqual(99.99);

});

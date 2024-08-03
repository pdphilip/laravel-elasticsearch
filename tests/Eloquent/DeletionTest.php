<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Schema\Schema;
use Workbench\App\Models\Product;

beforeEach(function () {
    Schema::deleteIfExists('products');
    Schema::create('products', function ($index) {
        $index->text('name');
        $index->float('price');
        $index->integer('status');
        $index->geo('manufacturer.location');
        $index->date('created_at');
        $index->date('updated_at');
        $index->date('deleted_at');
    });
});

test('delete a single model', function () {
    $product = Product::factory()->create();
    $retrieved = Product::find($product->_id);
    $retrieved->delete();
    $deleted = Product::find($product->_id);
    expect($deleted)->toBeNull();
});

test('mass deletion of models where color is null', function () {
    Product::factory(5)->state(['color' => null])->create();
    Product::factory(3)->state(['color' => 'blue'])->create();
    Product::whereNull('color')->delete();
    $products = Product::all();
    expect($products)->toHaveCount(3);
});

test('truncate all documents from an index', function () {
    Product::factory(10)->create();
    Product::truncate();
    sleep(1);

    $products = Product::all();
    expect($products)->toBeEmpty();
});

test('destroy a product by _id', function () {
    $product = Product::factory()->create();
    Product::destroy($product->_id);
    $deleted = Product::find($product->_id);
    expect($deleted)->toBeNull();
});

test('destroy multiple products by _ids', function () {
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    Product::destroy([$product1->_id, $product2->_id]);
    $deleted1 = Product::find($product1->_id);
    $deleted2 = Product::find($product2->_id);
    expect($deleted1)->toBeNull()
        ->and($deleted2)->toBeNull();
});

test('soft deletes a product and restores it', function () {
    $product = Product::factory()->create();
    $product->delete();
    $trashed = Product::withTrashed()->find($product->_id);
    expect($trashed->trashed())->toBeTrue();
    $trashed->restore();
    $restored = Product::find($product->_id);
    expect($restored->trashed())->toBeFalse();
});

test('ensure deletion of models with a specific status', function () {
    Product::factory(3)->state(['status' => 5])->create();
    Product::factory(2)->state(['status' => 1])->create();
    Product::where('status', 5)->delete();
    $remainingProducts = Product::all();
    expect($remainingProducts)->toHaveCount(2)
        ->and($remainingProducts->pluck('status')->contains(5))->toBeFalse();
});

test('delete multiple models by complex query', function () {
    Product::factory()->state(['is_active' => true, 'color' => 'blue'])->create();
    Product::factory()->state(['is_active' => false, 'color' => 'blue'])->create();
    Product::where('is_active', true)->where('color', 'blue')->delete();
    $activeBlue = Product::where('is_active', true)->where('color', 'blue')->first();
    expect($activeBlue)->toBeNull();
    $inactiveBlue = Product::where('is_active', false)->where('color', 'blue')->first();
    expect($inactiveBlue)->not()->toBeNull();
});

test('test soft deletion query visibility', function () {
    $product = Product::factory()->create();
    $product->delete();
    $visibleProduct = Product::find($product->_id);
    expect($visibleProduct)->toBeNull();
    $trashedProduct = Product::withTrashed()->find($product->_id);
    expect($trashedProduct)->not()->toBeNull()
        ->and($trashedProduct->trashed())->toBeTrue();
});

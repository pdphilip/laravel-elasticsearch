<?php

declare(strict_types=1);

ini_set('memory_limit', '1024M');

use Workbench\App\Models\Product;
use Workbench\App\Models\Soft;

test('delete a single model', function () {
    $product = Product::factory()->create();
    $retrieved = Product::find($product->id);
    $retrieved->delete();
    $deleted = Product::find($product->id);
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

    $products = Product::all();
    expect($products)->toBeEmpty();
});

test('destroy a product by id', function () {
    $product = Product::factory()->create();
    Product::destroy($product->id);
    $deleted = Product::find($product->id);
    expect($deleted)->toBeNull();
});

test('destroy multiple products by _ids', function () {
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    Product::destroy([$product1->id, $product2->id]);
    $deleted1 = Product::find($product1->id);
    $deleted2 = Product::find($product2->id);
    expect($deleted1)->toBeNull()
        ->and($deleted2)->toBeNull();
});

test('soft deletes a product and restores it', function () {
    $product = Soft::factory()->create();
    $product->delete();
    $trashed = Soft::withTrashed()->find($product->id);
    expect($trashed->trashed())->toBeTrue();
    $trashed->restore();
    $restored = Soft::find($product->id);
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
    $product = Soft::factory()->create();
    $product->delete();
    $visibleProduct = Soft::find($product->id);
    expect($visibleProduct)->toBeNull();
    $trashedProduct = Soft::withTrashed()->find($product->id);
    expect($trashedProduct)->not()->toBeNull()
        ->and($trashedProduct->trashed())->toBeTrue();
});

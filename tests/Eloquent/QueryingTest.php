<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Schema\Schema;
use Workbench\App\Models\Product;

beforeEach(function () {
    Schema::deleteIfExists('products');
    Schema::create('products', function ($index) {
        $index->text('name');
        $index->keyword('name');
        $index->float('price');
        $index->integer('status');
        $index->geo('manufacturer.location');
        $index->date('created_at');
        $index->date('updated_at');
    });
});

test('retrieve all products', function () {
    Product::factory()->count(5)->create();
    $products = Product::all();
    expect($products)->toBeCollection();
});

test('find a product by primary key', function () {
    $product = Product::factory()->create();
    $found = Product::find($product->_id);
    expect($found)->toBeInstanceOf(Product::class);
});

test('fail to find a product and get null', function () {
    $product = Product::find('nonexistent');
    expect($product)->toBeNull();
});

test('fail to find a product and get exception', function () {
    Product::findOrFail('nonexistent');
})->throws(Exception::class);

test('retrieve first product by status', function () {
    $product = Product::factory()->state(['status' => 1])->create();
    $found = Product::where('status', 1)->first();
    expect($found)->toBeInstanceOf(Product::class)
        ->and($found->status)->toEqual(1);
});

test('retrieve and count products using where condition', function () {
    Product::factory(5)->state(['status' => 1])->create();
    $products = Product::where('status', 1)->get();
    expect($products)->toHaveCount(5);
});

test('exclude products with specific status using whereNot', function () {
    Product::factory()->state(['status' => 1])->create();
    Product::factory()->state(['status' => 2])->create();
    $products = Product::whereNot('status', 1)->get();
    expect($products->first()->status)->not()->toEqual(1);
});

test('chain multiple conditions', function () {
    Product::factory()->state(['is_active' => true, 'in_stock' => 50])->create();
    $products = Product::where('is_active', true)->where('in_stock', '<=', 50)->get();
    expect($products)->toHaveCount(1);
});

test('use OR conditions', function () {
    Product::factory()->state(['is_active' => false, 'in_stock' => 150])->create();
    $products = Product::where('is_active', false)->orWhere('in_stock', '>=', 100)->get();
    expect($products)->toHaveCount(1);
});

test('check inclusion with whereIn', function () {
    Product::factory(3)->state(['status' => 1])->create();
    Product::factory(2)->state(['status' => 5])->create();
    $products = Product::whereIn('status', [1, 5])->get();
    expect($products)->toHaveCount(5);
});

test('check exclusion with whereNotIn', function () {
    Product::factory()->state(['color' => 'red'])->create();
    Product::factory()->state(['color' => 'green'])->create();
    $products = Product::whereNotIn('color', ['red', 'green'])->get();
    expect($products)->toBeEmpty();
});

test('query non-existent color field', function () {
    $products = Product::whereNull('color')->get();
    expect($products)->toBeCollection();
});

test('query products where color field exists', function () {
    Product::factory()->state(['color' => 'blue'])->create();
    $products = Product::whereNotNull('color')->get();
    expect($products->first()->color)->toEqual('blue');
});

test('filter products based on a date range', function () {
    Product::factory()->state(['created_at' => now()->subDays(5)])->create();
    $products = Product::whereBetween('created_at', [now()->subWeek(), now()])->get();
    expect($products)->toHaveCount(1);
});

test('retrieve products with no stock', function () {
    Product::factory()->state(['in_stock' => 0])->create();
    $products = Product::where('in_stock', 0)->get();
    expect($products)->toHaveCount(1);
});

test('calculate average orders correctly', function () {
    Product::factory()->state(['order_values' => [10, 20, 30]])->create();
    $product = Product::first();
    expect($product->getAvgOrdersAttribute())->toEqual(20);
});

test('search for products with partial text match', function () {
    Product::factory()->state(['name' => 'Black Coffee'])->create();
    $products = Product::where('name', 'like', 'bl')->orderBy('name.keyword')->get();
    expect($products)->toHaveCount(1)
        ->and($products->first()->name)->toEqual('Black Coffee');
});

test('complex query chaining', function () {
    Product::factory()->state(['type' => 'coffee', 'is_approved' => true])->create();
    Product::factory()->state(['type' => 'tea', 'is_approved' => false])->create();
    $products = Product::where('type', 'coffee')
        ->where('is_approved', true)
        ->orWhere('type', 'tea')
        ->where('is_approved', false)
        ->get();
    expect($products)->toHaveCount(2);
});

test('date query on product creation', function () {
    Product::factory()->state(['created_at' => now()->subDay()])->create();
    $products = Product::whereDate('created_at', now()->subDay()->toDateString())->get();
    expect($products)->toHaveCount(1);
});

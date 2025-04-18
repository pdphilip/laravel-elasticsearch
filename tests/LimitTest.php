<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Tests\Models\IdGenerated\Product;

beforeEach(function () {
    Product::executeSchema();

    Product::insert([
        ['product' => 'chocolate', 'price' => 5],
        ['product' => 'pumpkin', 'price' => 30],
        ['product' => 'apple', 'price' => 10],
        ['product' => 'orange juice', 'price' => 5],
        ['product' => 'coffee', 'price' => 15],
        ['product' => 'tea', 'price' => 12],
        ['product' => 'cookies', 'price' => 5],
        ['product' => 'ice cream', 'price' => 22],
        ['product' => 'bagel', 'price' => 8],
        ['product' => 'salad', 'price' => 14],
        ['product' => 'sandwich', 'price' => 5],
        ['product' => 'pizza', 'price' => 45],
        ['product' => 'water', 'price' => 5],
        ['product' => 'soda', 'price' => 5],
    ]);
});

it('tests limit clauses', function () {

    class ProductWithLimit extends Product
    {
        protected $defaultLimit = 7;
    }

    class ProductWithDefaultConnectionLimit extends Product
    {
        protected $connection = 'elasticsearch_with_default_limit';
    }

    $products = ProductWithLimit::all();
    expect($products)->toHaveCount(7);

    $products = ProductWithDefaultConnectionLimit::all();
    expect($products)->toHaveCount(4);

    $products = ProductWithDefaultConnectionLimit::limit(3)->get();
    expect($products)->toHaveCount(3);

    $products = ProductWithLimit::limit(3)->get();
    expect($products)->toHaveCount(3);
});

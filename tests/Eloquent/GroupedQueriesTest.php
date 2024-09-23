<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Factories\Sequence;
use Workbench\App\Models\Product;

beforeEach(function () {
    Product::truncate();
});

it('should find active or stock greater than 50', function () {
    $products = Product::factory(99)
        ->state(new Sequence(
            ['in_stock' => 51],
            ['in_stock' => 3],
            ['is_active' => true, 'in_stock' => 10],
        ))->make();
    Product::insert($products->toArray());

    $prods = Product::whereNested(function ($query) {
        $query->where('is_active', true)->orWhere('in_stock', '>', 50);
    })->get();

    $prodsAlt = Product::where(function ($query) {
        $query->where('is_active', true)->orWhere('in_stock', '>', 50);
    })->get();

    expect(count($prods))->toBeLessThanOrEqual(90)
        ->and(count($prodsAlt))->toBeLessThanOrEqual(90);

});

it('should find black and active or blue and inactive', function () {
    $products = Product::factory(100)
        ->state(new Sequence(
            ['is_active' => true, 'color' => 'black'],
            ['is_active' => true, 'color' => 'blue'],
            ['is_active' => false, 'color' => 'black'],
            ['is_active' => false, 'color' => 'blue'],
        ))->make();
    Product::insert($products->toArray());

    $prodsAlt = Product::where(function ($query) {
        $query->where('color', 'black')->where('is_active', true);
    })->orWhere(
        function ($query) {
            $query->where('color', 'blue')->where('is_active', false);
        }
    )->get();

    expect(count($prodsAlt))->toBeLessThanOrEqual(50);
});

it('should find (Black Or Blue) And (Status1 Or NotActive)', function () {
    $products = Product::factory(100)
        ->state(new Sequence(
            ['is_active' => true, 'color' => 'black', 'status' => 2],
            ['is_active' => true, 'color' => 'blue', 'status' => 2],
            ['is_active' => false, 'color' => 'black', 'status' => 2],
            ['is_active' => false, 'color' => 'blue', 'status' => 2],
            ['is_active' => true, 'color' => 'black', 'status' => 1],
            ['is_active' => true, 'color' => 'blue', 'status' => 1],
            ['is_active' => false, 'color' => 'black', 'status' => 1],
            ['is_active' => false, 'color' => 'blue', 'status' => 1],
        ))->make();
    Product::insert($products->toArray());

    $prods = Product::whereNested(function ($query) {
        $query->where('color', 'black')->orWhere('color', 'blue');
    })->whereNested(function ($query) {
        $query->where('status', 1)->orWhere('is_active', false);
    }
    )->get();

    expect(count($prods))->toBeLessThanOrEqual(74);

    $prodsAlt = Product::where(function ($query) {
        $query->where('color', 'black')->orWhere('color', 'blue');
    })->where(function ($query) {
        $query->where('status', 1)->orWhere('is_active', false);
    }
    )->get();

    expect(count($prodsAlt))->toBeLessThanOrEqual(74);
});

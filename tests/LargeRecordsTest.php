<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Tests\Models\Product;

beforeEach(function () {
    Product::executeSchema();

});

it('tests track total hits', function () {

    Product::buildRecords(11_000);

    class ProductWithDefaultTrackTotalHits extends Product
    {
        protected $connection = 'elasticsearch_with_default_track_total_hits';
    }

    $products = Product::limit(1)->get();
    expect($products->getQueryMeta()->getTotalHits())->toBe(10000);

    $products = Product::limit(1)->withTrackTotalHits()->get();
    expect($products->getQueryMeta()->getTotalHits())->toBe(11000);

    $products = Product::limit(1)->withTrackTotalHits(false)->get();
    expect($products->getQueryMeta()->getTotalHits())->toBe(-1);

    $products = Product::limit(1)->withTrackTotalHits(300)->get();
    expect($products->getQueryMeta()->getTotalHits())->toBe(300);

    $products = ProductWithDefaultTrackTotalHits::limit(1)->get();
    expect($products->getQueryMeta()->getTotalHits())->toBe(11000);

    $products = ProductWithDefaultTrackTotalHits::limit(1)->withoutTrackTotalHits()->get();
    expect($products->getQueryMeta()->getTotalHits())->toBe(10000);

    $products = ProductWithDefaultTrackTotalHits::limit(1)->withTrackTotalHits(300)->get();
    expect($products->getQueryMeta()->getTotalHits())->toBe(300);

});

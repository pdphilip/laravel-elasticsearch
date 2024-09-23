<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Collection\ElasticCollection;
use PDPhilip\Elasticsearch\Meta\QueryMetaData;
use Workbench\App\Models\Product;

test('bulk insert returns a Elastic Collection', function () {
    $products = Product::factory(10)->make();
    $result = Product::insert($products->toArray(), true);

    expect($result)->toBeInstanceOf(ElasticCollection::class)
        ->and($result->getQueryMetaAsArray())->toBeArray();
});

test('bulk insert without refresh', function () {
    $products = Product::factory(1000)->make();
    $result = Product::insertWithoutRefresh($products->toArray());
    expect($result)->toBeInstanceOf(ElasticCollection::class)
        ->and($result->getDsl())->toBeArray()
        ->and($result->getDsl())->toHaveKeys(['query', 'dsl'])
        ->and($result->getTook())->toBeInt()
        ->and($result->getTotal())->toBeInt()
        ->and($result->getMaxScore())->toBeString()
        ->and($result->getResults())->toBeArray()
        ->and($result->getQueryMetaAsArray())->toBeArray()
        ->and($result->getQueryMeta())->toBeInstanceOf(QueryMetaData::class);
    sleep(2);
    expect(Product::count())->toBe(1000);
});

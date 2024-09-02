<?php

declare(strict_types=1);

  use PDPhilip\Elasticsearch\Collection\ElasticCollection;
  use Workbench\App\Models\Product;

test('returns a Elastic Collection', function () {
    $products = Product::factory(100)->make();
    $result = Product::insert($products->toArray(), true);

    expect($result)->toBeInstanceOf(ElasticCollection::class)
                   ->and($result->getQueryMetaAsArray())->toBeArray();
});

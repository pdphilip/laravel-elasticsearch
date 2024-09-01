<?php

namespace PDPhilip\Elasticsearch\Collection;

use Illuminate\Support\LazyCollection;

class LazyElasticCollection extends LazyCollection
{
    use ElasticCollectionMeta;
}

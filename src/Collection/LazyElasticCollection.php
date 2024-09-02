<?php

namespace PDPhilip\Elasticsearch\Collection;

use Illuminate\Support\LazyCollection;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @extends \Illuminate\Support\LazyCollection<TKey, TValue>
 */
class LazyElasticCollection extends LazyCollection
{
    use ElasticCollectionMeta;
}

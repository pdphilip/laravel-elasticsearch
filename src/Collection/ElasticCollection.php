<?php

namespace PDPhilip\Elasticsearch\Collection;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection;

/**
 * @template TKey of array-key
 * @template TModel of \PDPhilip\Elasticsearch\Eloquent\Model
 *
 * @extends \Illuminate\Database\Eloquent\Collection<int, TModel>
 */
class ElasticCollection extends Collection
{
    use ElasticCollectionMeta;

    /**
     * @param  Arrayable<TKey, TModel>|iterable<TKey, TModel>|array<TKey|int, mixed>|null  $items
     */
    public function __construct($items = [])
    {
        parent::__construct($items);
    }
}

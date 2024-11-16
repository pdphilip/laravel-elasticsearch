<?php

namespace PDPhilip\Elasticsearch\Traits\Eloquent;

use PDPhilip\Elasticsearch\Collection\ElasticCollection;

trait HasCollection
{
    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array<array-key, \PDPhilip\Elasticsearch\Eloquent\Model>  $models
     * @return \PDPhilip\Elasticsearch\Collection\ElasticCollection<array-key, \PDPhilip\Elasticsearch\Eloquent\Model>;
     */
    public function newCollection(array $models = []): ElasticCollection
    {
        return new ElasticCollection($models);
    }
}

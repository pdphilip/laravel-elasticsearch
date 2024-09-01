<?php

namespace PDPhilip\Elasticsearch\Eloquent;

use PDPhilip\Elasticsearch\Collection\ElasticCollection;

trait HasCollection
{
    /**
     * Create a new Eloquent Collection instance.
     *
     * @param array<array-key, \PDPhilip\Elasticsearch\Eloquent\Model> $models
     * @return \PDPhilip\Elasticsearch\Collection\ElasticCollection;
     */
    public function newCollection(array $models = [])
    {
        return new ElasticCollection($models);
    }
}

<?php

namespace PDPhilip\Elasticsearch\Collection;

use PDPhilip\Elasticsearch\Meta\QueryMetaData;

trait ElasticCollectionMeta
{
    protected QueryMetaData $meta;

    public function setQueryMeta(QueryMetaData $meta): void
    {
        $this->meta = $meta;
    }

    public function getQueryMeta()
    {
        return $this->meta;
    }

    public function getQueryMetaAsArray()
    {
        return $this->meta->asArray();
    }

    public function getDsl()
    {
        return [
            'query' => $this->meta->getQuery(),
            'dsl' => $this->meta->getDsl(),
        ];
    }
}

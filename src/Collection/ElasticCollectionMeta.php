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

    public function getQueryMeta(): QueryMetaData
    {
        return $this->meta;
    }

    public function getQueryMetaAsArray(): array
    {
        return $this->meta->asArray();
    }

    public function getDsl(): array
    {
        return [
            'query' => $this->meta->getQuery(),
            'dsl' => $this->meta->getDsl(),
        ];
    }

    public function getResults(): array
    {
        return $this->meta->getResults();
    }
}

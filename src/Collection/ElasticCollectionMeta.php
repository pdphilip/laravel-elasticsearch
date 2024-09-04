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

    public function getTook(): int
    {
        return $this->meta->getTook();
    }

    public function getShards(): mixed
    {
        return $this->meta->getShards();
    }

    public function getTotal(): int
    {
        return $this->meta->getTotal();
    }

    public function getMaxScore(): string
    {
        return $this->meta->getMaxScore();
    }

    public function getResults(): array
    {
        return $this->meta->getResults();
    }
}

<?php

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Database\Eloquent\Collection;
use PDPhilip\Elasticsearch\Data\MetaDTO;
use PDPhilip\Elasticsearch\Data\QueryMeta;

/**
 * @template TKey of array-key
 * @template TModel of \PDPhilip\Elasticsearch\Eloquent\Model
 *
 * @extends Collection<TKey, TModel>
 */
class ElasticCollection extends Collection
{
    protected ?QueryMeta $meta = null;

    public static function loadCollection(Collection $collection)
    {
        return new static($collection->all());
    }

    public function loadMeta(QueryMeta $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function setQueryMeta(MetaDTO $meta): self
    {
        $this->meta = new QueryMeta($meta);

        return $this;
    }

    /**
     * Whether this collection carries the meta of the query that built it.
     *
     * False for the collections Eloquent builds on its own: newCollection(),
     * hydrate(), and anything derived through map(), filter() or values().
     */
    public function hasQueryMeta(): bool
    {
        return $this->meta !== null;
    }

    /**
     * Meta of the query that built this collection.
     *
     * Falls back to an empty QueryMeta, which reports its unknowns as -1, so
     * that a collection built without a query still answers every getter. The
     * fallback is not retained: reading meta that was never set must not make
     * hasQueryMeta() start answering true.
     */
    public function getQueryMeta(): QueryMeta
    {
        return $this->meta ?? new QueryMeta;
    }

    public function getQueryMetaAsArray(): array
    {
        return $this->getQueryMeta()->toArray();
    }

    public function getDsl(): array
    {
        return [
            'query' => $this->getQueryMeta()->getQuery(),
            'dsl' => $this->getQueryMeta()->getDsl(),
        ];
    }

    public function getTook(): int
    {
        return $this->getQueryMeta()->getTook();
    }

    public function getShards(): mixed
    {
        return $this->getQueryMeta()->getShards();
    }

    public function getTotal(): int
    {
        return $this->getQueryMeta()->getTotal();
    }

    public function getMaxScore(): string
    {
        return $this->getQueryMeta()->getMaxScore();
    }

    public function getResults(): array
    {
        return $this->getQueryMeta()->getResults();
    }

    public function getPitId()
    {
        return $this->getQueryMeta()->getPitId();
    }

    public function getAfterKey()
    {
        return $this->getQueryMeta()->getAfterKey();
    }
}

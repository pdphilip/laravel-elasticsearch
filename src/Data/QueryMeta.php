<?php

namespace PDPhilip\Elasticsearch\Data;

/**
 * Collection-level query metadata.
 *
 * Wraps a MetaDTO (the raw ES response transfer object) and provides
 * typed getters for collection-level properties. Used by ElasticCollection.
 *
 * Data flow: ES response -> Processor -> MetaDTO -> QueryMeta -> ElasticCollection
 */
final class QueryMeta
{
    protected ?MetaDTO $meta;

    public function __construct(?MetaDTO $meta = null)
    {
        $this->meta = $meta;
    }

    // ----------------------------------------------------------------------
    // Getters — delegate to MetaDTO
    // ----------------------------------------------------------------------

    public function getIndex(): string
    {
        return $this->meta?->getIndex() ?? '';
    }

    public function getTook(): int
    {
        return $this->meta?->getTook() ?? -1;
    }

    public function getTotal(): int
    {
        return $this->meta?->getTotal() ?? -1;
    }

    public function getTotalHits(): int
    {
        return $this->meta?->getHits() ?? -1;
    }

    public function getMaxScore(): string
    {
        return (string) ($this->meta?->getMaxScore() ?? '');
    }

    public function getShards(): mixed
    {
        return $this->meta?->getShards() ?? [];
    }

    public function getQuery(): mixed
    {
        return $this->meta?->getQuery() ?? '';
    }

    public function getDsl(): array
    {
        return $this->meta?->getDsl() ?? [];
    }

    public function getSort(): ?array
    {
        return $this->meta?->getSort() ?? [];
    }

    public function getCursor(): ?array
    {
        return $this->meta?->getCursor() ?? [];
    }

    public function getPitId(): mixed
    {
        return $this->meta?->getPitId();
    }

    public function getAfterKey(): mixed
    {
        return $this->meta?->getAfterKey() ?? [];
    }

    public function getResults($key = null): mixed
    {
        if ($key) {
            return null;
        }

        return [];
    }

    public function toArray(): array
    {
        if (! $this->meta) {
            return [];
        }

        $return = [
            'index' => $this->getIndex(),
            'query' => $this->getQuery(),
            'took' => $this->getTook(),
            'total' => $this->getTotal(),
            'hits' => $this->getTotalHits(),
        ];

        $maxScore = $this->getMaxScore();
        if ($maxScore !== '') {
            $return['max_score'] = $maxScore;
        }
        $shards = $this->getShards();
        if ($shards) {
            $return['shards'] = $shards;
        }
        $dsl = $this->getDsl();
        if ($dsl) {
            $return['dsl'] = $dsl;
        }
        $sort = $this->getSort();
        if ($sort) {
            $return['sort'] = $sort;
        }
        $cursor = $this->getCursor();
        if ($cursor) {
            $return['cursor'] = $cursor;
        }

        $return['_meta'] = $this->meta->toArray();

        return $return;
    }
}

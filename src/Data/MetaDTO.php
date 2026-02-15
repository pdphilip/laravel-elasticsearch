<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use PDPhilip\Elasticsearch\Traits\Makeable;

/**
 * Raw ES response transfer object.
 *
 * Created by the Processor from ES response arrays. Carries response data
 * to QueryMeta (collection-level) and ModelMeta (per-model) via their setters.
 *
 * The set() method allows the Processor to add computed values (query, dsl, total, after_key)
 * after construction.
 */
class MetaDTO implements Arrayable
{
    use Makeable;

    protected array $result;

    public function __construct(array $result, array $extra = [])
    {

        unset($result['hits']['hits'], $result['aggregations']);

        $this->result = [
            ...$result,
            ...$extra,
        ];
    }

    // ----------------------------------------------------------------------
    // Model Level
    // ----------------------------------------------------------------------

    public function getDocCount(): ?int
    {
        return Arr::get($this->result, 'doc_count');
    }

    public function getHighlight($column, $deliminator = ''): ?string
    {
        return implode($deliminator, Arr::get($this->result, "highlight.{$column}", []));
    }

    public function getHighlights(): ?array
    {
        return Arr::get($this->result, 'highlight', []);
    }

    public function getIndex(): ?string
    {
        return Arr::get($this->result, '_index', '');
    }

    public function getBucket(): ?array
    {
        return Arr::get($this->result, 'bucket', []);
    }

    // ----------------------------------------------------------------------
    //  Collection Level
    // ----------------------------------------------------------------------

    public function getTook(): ?int
    {
        return Arr::get($this->result, 'took', 0);
    }

    public function getHits()
    {
        return Arr::get($this->result, 'hits.total.value', -1);
    }

    public function getTotal()
    {
        return Arr::get($this->result, 'total', -1);
    }

    public function getMaxScore()
    {
        return Arr::get($this->result, 'hits.max_score', -1);
    }

    public function getDsl()
    {
        return Arr::get($this->result, 'dsl', []);
    }

    public function getPitId()
    {
        return Arr::get($this->result, 'pit_id', null);
    }

    public function getAfterKey()
    {
        return Arr::get($this->result, 'after_key', []);
    }

    public function getQuery()
    {
        return Arr::get($this->result, 'query', []);
    }

    public function getScore(): mixed
    {
        return Arr::get($this->result, '_score', 0);
    }

    public function getShards(): ?array
    {
        return Arr::get($this->result, '_shards', []);
    }

    public function getCursor(): ?array
    {
        return Arr::get($this->result, 'cursor', []);
    }

    public function getSort(): ?array
    {
        return Arr::get($this->result, 'sort', []);
    }

    // ----------------------------------------------------------------------
    // Universal
    // ----------------------------------------------------------------------
    public function toArray(): array
    {
        return $this->result;
    }

    public function set($key, $value): static
    {
        Arr::set($this->result, $key, $value);

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use PDPhilip\Elasticsearch\Traits\Makeable;

class MetaTransfer implements Arrayable
{
    use Makeable;

    protected array $result;

    public function __construct(array $result, array $extra)
    {

        unset($result['hits']['hits'], $result['aggregations']);

        $this->result = [
            ...$result,
            ...$extra,
        ];
    }

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
        return Arr::get($this->result, '_index');
    }

    public function getTook(): ?int
    {
        return Arr::get($this->result, 'took', 0);
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

    public function toArray(): array
    {
        return $this->result;
    }
}

<?php

namespace PDPhilip\Elasticsearch\Meta;

final class ModelMetaData
{
    protected string $score = '';

    protected string $index = '';

    protected mixed $_id = '';

    protected array $sort = [];

    protected array $_dsl = [];

    protected array $cursor = [];

    protected array $highlights = [];

    public array $_query = [];

    public function __construct($meta)
    {
        if (isset($meta['score'])) {
            $this->score = $meta['score'];
        }
        if (isset($meta['index'])) {
            $this->index = $meta['index'];
        }
        if (isset($meta['sort'])) {
            $this->sort = $meta['sort'];
        }
        if (isset($meta['cursor'])) {
            $this->cursor = $meta['cursor'];
        }
        if (isset($meta['_id'])) {
            $this->_id = $meta['_id'];
        }
        if (isset($meta['_query'])) {
            $this->_query = $meta['_query'];
        }
        if (isset($meta['dsl'])) {
            $this->_dsl = $meta['dsl'];
        }
        if (isset($meta['highlights'])) {
            $this->highlights = $meta['highlights'];
        }
    }

    //----------------------------------------------------------------------
    // Getters
    //----------------------------------------------------------------------

    public function getIndex()
    {
        return $this->index;
    }

    public function getScore()
    {
        return $this->score;
    }

    public function getId(): mixed
    {
        return $this->_id ?? null;
    }

    public function getSort(): ?array
    {
        return $this->sort;
    }

    public function getCursor(): ?array
    {
        return $this->cursor;
    }

    public function getQuery()
    {
        return $this->_query;
    }

    public function getHighlights(): array
    {
        return $this->highlights ?? [];
    }

    public function parseHighlights($data = []): ?object
    {
        if ($this->highlights) {
            $this->_mergeFlatKeysIntoNestedArray($data, $this->highlights);

            return (object) $data;
        }

        return null;
    }

    public function asArray(): array
    {
        return [
            'score' => $this->score,
            'index' => $this->index,
            '_id' => $this->_id,
            'sort' => $this->sort,
            'cursor' => $this->cursor,
            '_query' => $this->_query,
            '_dsl' => $this->_dsl,
            'highlights' => $this->highlights,
        ];
    }

    //----------------------------------------------------------------------
    // Setters
    //----------------------------------------------------------------------
    public function setId($id): void
    {
        $this->_id = $id;
    }

    public function setSort(array $sort): void
    {
        $this->sort = $sort;
    }

    public function setCursor(array $cursor): void
    {
        $this->cursor = $cursor;
    }

    //----------------------------------------------------------------------
    // Helpers
    //----------------------------------------------------------------------

    protected function _mergeFlatKeysIntoNestedArray(&$data, $attrs): void
    {
        foreach ($attrs as $key => $value) {
            if ($value) {
                $value = implode('......', $value);
                $parts = explode('.', $key);
                $current = &$data;

                foreach ($parts as $partIndex => $part) {
                    if ($partIndex === count($parts) - 1) {
                        $current[$part] = $value;
                    } else {
                        if (! isset($current[$part]) || ! is_array($current[$part])) {
                            $current[$part] = [];
                        }
                        $current = &$current[$part];
                    }
                }
            }
        }
    }
}

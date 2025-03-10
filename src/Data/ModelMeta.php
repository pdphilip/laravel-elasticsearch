<?php

namespace PDPhilip\Elasticsearch\Data;

use Illuminate\Support\Arr;

final class ModelMeta
{
    protected string $recordIndex = '';

    protected string $table = '';

    protected string $tablePrefix = '';

    protected string $tableSuffix = '';

    protected mixed $score = '';

    protected array $highlights = [];

    protected array $sort = [];

    protected array $cursor = [];

    protected ?int $docCount = null;

    //    public array $_query = []; //TBD

    public function __construct($table, $tablePrefix)
    {
        $this->table = $table;
        $this->tablePrefix = $tablePrefix;
    }

    // ----------------------------------------------------------------------
    // Getters
    // ----------------------------------------------------------------------

    public function getFullTable()
    {
        return $this->tablePrefix.$this->table.$this->tableSuffix;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getTableSuffix()
    {
        return $this->tableSuffix;
    }

    public function getRecordIndex()
    {
        return $this->recordIndex;
    }

    public function getScore()
    {
        return $this->score;
    }

    public function getSort(): array
    {
        return $this->sort;
    }

    public function getCursor(): array
    {
        return $this->cursor;
    }

    public function getHighlights(): array
    {
        return $this->highlights ?? [];
    }

    public function getHighlight($column, $deliminator = ''): ?string
    {
        return implode($deliminator, Arr::get($this->highlights, $column));
    }

    public function getDocCount(): ?int
    {
        return $this->docCount;
    }

    public function parseHighlights($data = []): ?object
    {
        if ($this->highlights) {
            $this->_mergeFlatKeysIntoNestedArray($data, $this->highlights);
        }

        return (object) $data;
    }

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'index' => $this->recordIndex,
            'table_prefix' => $this->tablePrefix,
            'table' => $this->table,
            'table_suffix' => $this->tableSuffix,
            'doc_Count' => $this->docCount,
            'sort' => $this->sort,
            'cursor' => $this->cursor,
            'highlights' => $this->highlights,
        ];
    }

    // ----------------------------------------------------------------------
    // Setters
    // ----------------------------------------------------------------------
    //    public function setId($id): void
    //    {
    //        $this->_id = $id;
    //    }

    public function setTable($table): void
    {
        $this->table = $table;
    }

    public function setMeta(?MetaDTO $meta = null): void
    {
        if (! $meta) {
            return;
        }
        $this->score = $meta->getScore();
        $this->highlights = $meta->getHighlights();
        $this->setRecordIndex($meta->getIndex());
        $this->setCursor($meta->getCursor());
        $this->setSort($meta->getSort());
        $this->docCount = $meta->getDocCount();
    }

    public function setSort(array $sort): void
    {
        $this->sort = $sort;
    }

    public function setCursor(array $cursor): void
    {
        $this->cursor = $cursor;
    }

    public function setRecordIndex($index)
    {
        if ($index) {
            $this->recordIndex = $index;
            $suffix = str_replace($this->tablePrefix.$this->table, '', $index);
            $this->setTableSuffix($suffix);
        }

    }

    public function setTableSuffix($suffix): void
    {
        $this->tableSuffix = $suffix;
    }

    // ----------------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------------

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

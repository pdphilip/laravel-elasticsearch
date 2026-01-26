<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query\Concerns;

use Closure;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Exceptions\RuntimeException;

/**
 * Nested object and parent/child relationship queries.
 * For when your docs have docs inside docs.
 */
trait BuildsNestedQueries
{
    /**
     * Query nested objects within a document.
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-nested-query.html
     */
    public function whereNestedObject($column, $query, $filterInnerHits = false, $options = [], $boolean = 'and', $not = false): self
    {
        $from = $this->from;
        $type = 'NestedObject';
        $options = $this->setOptions($options, 'nested');
        if ($filterInnerHits) {
            $options->innerHits(true);
        }
        $options = $options->toArray();

        $this->options()->add('parentField', $column);
        if (! is_string($query) && is_callable($query)) {
            call_user_func($query, $query = $this->newQuery($from));
        }

        $this->wheres[] = compact('column', 'query', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    public function orWhereNestedObject($column, $query, $filterInnerHits = false, $options = []): self
    {
        return $this->whereNestedObject($column, $query, $filterInnerHits, $options, 'or');
    }

    public function whereNotNestedObject($column, $query, $filterInnerHits = false, $options = []): self
    {
        return $this->whereNestedObject($column, $query, $filterInnerHits, $options, 'and', true);
    }

    public function orWhereNotNestedObject($column, $query, $filterInnerHits = false, $options = []): self
    {
        return $this->whereNestedObject($column, $query, $filterInnerHits, $options, 'or', true);
    }

    /**
     * Add a nested filter with inner_hits support.
     * Only one per nested path allowed.
     */
    public function filterNested($column, $query, $options = [])
    {
        $currentWheres = collect($this->wheres);
        $existing = $currentWheres->where('type', 'InnerNested')->where('column', $column)->first();
        if ($existing) {
            throw new RuntimeException("Nested filter for field '{$column}' already exists.");
        }

        $boolean = 'and';
        $not = false;
        $from = $this->from;
        $type = 'InnerNested';
        $options = $this->setOptions($options, 'nested');
        $options = $options->toArray();

        $this->options()->add('parentField', $column);
        if (! is_string($query) && is_callable($query)) {
            call_user_func($query, $query = $this->newQuery($from));
        }

        $this->wheres[] = compact('column', 'query', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    /**
     * Order by a field within nested objects.
     */
    public function orderByNested(string $column, $direction = 1, string $mode = 'avg'): self
    {
        $options = ['mode' => $mode];
        $options = [
            ...$options,
            'nested' => ['path' => Str::beforeLast($column, '.')],
        ];

        return $this->orderBy($column, $direction, $options);
    }

    public function orderByNestedDesc(string $column, string $mode = 'avg'): self
    {
        return $this->orderByNested($column, 'desc', $mode);
    }

    // ----------------------------------------------------------------------
    // Parent/Child Relationships
    // ----------------------------------------------------------------------

    /**
     * Query child documents that match criteria.
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-has-child-query.html
     */
    public function whereChild(
        string $documentType,
        Closure $callback,
        array $options = [],
        string $boolean = 'and'
    ): self {
        return $this->whereRelationship('child', $documentType, $callback, $options, $boolean);
    }

    /**
     * Query parent documents that match criteria.
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-has-parent-query.html
     */
    public function whereParent(
        string $documentType,
        Closure $callback,
        array $options = [],
        string $boolean = 'and'
    ): self {
        return $this->whereRelationship('parent', $documentType, $callback, $options, $boolean);
    }

    /**
     * Query by parent document ID.
     */
    public function whereParentId(string $parentType, $id, string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'ParentId',
            'parentType' => $parentType,
            'id' => $id,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Internal method for parent/child relationship queries.
     */
    protected function whereRelationship(
        string $relationshipType,
        string $documentType,
        Closure $callback,
        array $options = [],
        string $boolean = 'and'
    ): self {
        call_user_func($callback, $query = $this->newQuery());

        $this->wheres[] = [
            'type' => ucfirst($relationshipType),
            'documentType' => $documentType,
            'value' => $query,
            'options' => $options,
            'boolean' => $boolean,
        ];

        return $this;
    }

    // ----------------------------------------------------------------------
    // Deprecated
    // ----------------------------------------------------------------------

    /**
     * @deprecated v5.0.0
     * @see filterNested()
     */
    public function queryNested($column, $callBack)
    {
        return $this->filterNested($column, $callBack);
    }
}

<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

use Closure;
use Illuminate\Database\Eloquent\Builder as BaseEloquentBuilder;
use Iterator;
use PDPhilip\Elasticsearch\Helpers\QueriesRelationships;
use PDPhilip\Elasticsearch\Query\Builder as QueryBuilder;
use PDPhilip\Elasticsearch\Schema\Schema;

/**
 * @property QueryBuilder $query
 * @property Model $model
 *
 * @template TModel of Model
 */
class Builder extends BaseEloquentBuilder
{
    use QueriesRelationships;

    protected $type;

    protected $passthru = [
        'aggregate',
        'average',
        'avg',
        'count',
        'dd',
        'ddrawsql',
        'doesntexist',
        'doesntexistor',
        'dump',
        'dumprawsql',
        'exists',
        'existsor',
        'explain',
        'getbindings',
        'getconnection',
        'getgrammar',
        'getrawbindings',
        'implode',
        'insert',
        'insertgetid',
        'insertorignore',
        'insertusing',
        'insertorignoreusing',
        'max',
        'min',
        'raw',
        'rawvalue',
        'sum',
        'tosql',
        'torawsql',

        //ES Metric Aggregations
        'stats',
        'extendedstats',
        'medianabsolutedeviation',
        'percentiles',
        'stringstats',
        'cardinality',
        'matrix',
        'boxplot',

        //ES Bucket Aggregations
        'bucket',
        'bucketAggregation',
    ];

    /**
     * Set a model instance for the model being queried.
     *
     * @param  Model  $model
     * @return $this
     */
    public function setModel($model): static
    {
        $this->model = $model;

        $this->query->from($model->getTable());
        $this->query->options()->set($this->model?->options()->all() ?? []);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function newModelInstance($attributes = [])
    {
        $model = $this->model->newInstance($attributes)->setConnection(
            $this->query->getConnection()->getName()
        );

        // Merge in our options.
        $model->options()->merge(
            $this->model?->options()->all() ?? [],
            $this->query?->options()->all() ?? []
        );

        return $model;
    }

    public function query(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and', $options = [])
    {
        if ($column instanceof Closure && is_null($operator)) {
            $column($query = $this->model->newQueryWithoutRelationships());

            $this->query->addNestedWhereQuery($query->getQuery(), $boolean);
        } else {
            $this->query->where(...func_get_args());
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get($columns = ['*'])
    {
        $builder = $this->applyScopes();

        $models = $builder->getModels($columns);

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded, which will solve the
        // n+1 query issue for the developers to avoid running a lot of queries.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $builder->getModel()->newCollection($models);
    }

    /**
     * @param  string  $columns
     */
    public function count($columns = '*'): int
    {
        return $this->toBase()->getCountForPagination($columns);
    }

    /**
     * Get the hydrated models without eager loading.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Model[]
     */
    public function getModels($columns = ['*'])
    {
        return $this->model->hydrate(
            $this->query->get($columns)->all()
        )->all();
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate(array $items)
    {
        $instance = $this->newModelInstance();

        return $instance->newCollection(array_map(function ($item) use ($instance) {
            return $instance->newFromBuilder($item, $this->getConnection()->getName());
        }, $items));
    }

    /**
     * {@inheritdoc}
     */
    public function chunk($count, callable $callback, $scrollTimeout = '30s')
    {
        $this->enforceOrderBy();

        foreach ($this->query->connection->searchResponseIterator($this->query->toCompiledQuery(), $scrollTimeout, $count) as $results) {
            $page = $results['_scroll_id'];
            $results = $this->model->hydrate(
                $this->query->processor->processSelect($this->query, $results)
            );

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if ($callback($results, $page) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get a generator for the given query.
     *
     * @return Iterator
     */
    public function cursor($scrollTimeout = '30s')
    {
        foreach ($this->applyScopes()->query->cursor($scrollTimeout) as $record) {
            yield $this->model->newFromBuilder($record);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findOrNew($id, $columns = ['*']): Model
    {
        $model = parent::findOrNew($id, $columns);
        $model['id'] = $id; //set the id to the model

        return $model;
    }

    public function withoutRefresh()
    {
        $this->model->options()->add('refresh', false);

        return $this->model;
    }

    public function withSuffix($suffix)
    {
        $this->model->options()->add('suffix', $suffix);

        return $this->model;
    }

    //----------------------------------------------------------------------
    // Schema operations
    //----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    //    public function truncate(): int
    //    {
    //        $result = $this->connection->deleteAll([]);
    //
    //        if ($result->isSuccessful()) {
    //            return $result->getDeletedCount();
    //        }
    //
    //        return 0;
    //    }

    public function deleteIndex(): void
    {
        Schema::connection($this->query->connection->getName())->drop($this->from);
    }

    public function deleteIndexIfExists(): void
    {
        Schema::connection($this->query->connection->getName())->dropIfExists($this->from);
    }

    public function getIndexMappings(bool $flatten = true): array
    {
        return Schema::on($this->query->connection->getName())->getMappings($this->from, $flatten);
    }

    public function getFieldMappings(bool $flatten = true): array
    {
        return Schema::connection($this->query->connection->getName())->getFieldMapping($this->from, '*', $flatten);
    }

    public function getFieldMapping(string|array $field = '*', bool $flatten = true): array
    {
        return Schema::connection($this->query->connection->getName())->getFieldMapping($this->from, $field, $flatten);
    }

    public function getIndexSettings(): array
    {
        return Schema::connection($this->query->connection->getName())->getSettings($this->from);
    }

    public function createIndex(?Closure $callback = null): bool
    {
        if (! $this->indexExists()) {
            Schema::connection($this->query->connection->getName())->create($this->from, $callback);

            return true;
        }

        return false;
    }

    public function indexExists(): bool
    {
        return Schema::connection($this->query->connection->getName())->hasTable($this->getModel()->getTable());
    }

    public function hasField(string $column): bool
    {
        return Schema::connection($this->query->connection->getName())->hasColumn($this->from, $column);
    }

    public function hasFields(array $columns): bool
    {
        return Schema::connection($this->query->connection->getName())->hasColumns($this->from, $columns);
    }
}

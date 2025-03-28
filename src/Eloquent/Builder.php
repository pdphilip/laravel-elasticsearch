<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

use Closure;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder as BaseEloquentBuilder;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Iterator;
use PDPhilip\Elasticsearch\Data\MetaDTO;
use PDPhilip\Elasticsearch\Exceptions\BuilderException;
use PDPhilip\Elasticsearch\Exceptions\DynamicIndexException;
use PDPhilip\Elasticsearch\Exceptions\RuntimeException;
use PDPhilip\Elasticsearch\Helpers\QueriesRelationships;
use PDPhilip\Elasticsearch\Pagination\SearchAfterPaginator;
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

    protected $queryMeta;

    protected $type;

    protected $model;

    protected $passthru = [
        'average',
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
        'raw',
        'rawvalue',
        'tosql',
        'torawsql',

        // ES
        'todsl',
        'bucket',
        'bucketaggregation',
        'openpit',
        'bulkinsert',
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
        $this->query->options()->merge($this->model?->options()->all() ?? []);

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
    public function get($columns = ['*']): ElasticCollection
    {
        if (! is_array($columns)) {
            $columns = [$columns];
        }
        $builder = $this->applyScopes();
        $modelsCollection = $builder->getElasticModels($columns);
        $models = $modelsCollection->all();

        $models = $this->loadRelations($models, $builder);

        return ElasticCollection::loadCollection($builder->getModel()->newCollection($models))->loadMeta($modelsCollection->getQueryMeta());
    }

    public function getPit($columns = ['*'])
    {
        if (! is_array($columns)) {
            $columns = [$columns];
        }
        $builder = $this->applyScopes();
        $modelsCollection = $builder->getElasticModelsViaPit($columns);
        $models = $modelsCollection->all();
        $models = $this->loadRelations($models, $builder);

        return ElasticCollection::loadCollection($builder->getModel()->newCollection($models))->loadMeta($modelsCollection->getQueryMeta());
    }

    public function getRaw(): mixed
    {
        return $this->query->getRaw();
    }

    /**
     * @param  ?string  $columns
     */
    public function count($columns = null): int
    {
        $builder = $this->applyScopes();

        return $builder->query->count($columns);
        //        return $this->toBase()->getCountForPagination($columns);
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

    public function getElasticModels($columns = ['*'])
    {
        $elasticQueryCollection = $this->query->get($columns);
        $eloquentCollection = $this->model->hydrate(
            $elasticQueryCollection->all()
        );

        return ElasticCollection::loadCollection($eloquentCollection)->loadMeta($elasticQueryCollection->getQueryMeta());
    }

    public function getElasticModelsViaPit($columns = ['*'])
    {
        $elasticQueryCollection = $this->query->getPit($columns);
        $eloquentCollection = $this->model->hydrate(
            $elasticQueryCollection->all()
        );

        return ElasticCollection::loadCollection($eloquentCollection)->loadMeta($elasticQueryCollection->getQueryMeta());
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
     *
     * @throws BuilderException
     */
    public function chunk($count, callable $callback, $scrollTimeout = '30s')
    {
        if (! $this->query->connection->allowIdSort) {
            return $this->chunkByPit($count, $callback);
        }
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
     * Chunk the results of a query by comparing IDs in a given order.
     *
     * @param  int  $count
     * @param  string|null  $column
     * @param  string|null  $alias
     * @param  bool  $descending
     *
     * @throws BuilderException
     */
    public function orderedChunkById($count, callable $callback, $column = null, $alias = null, $descending = false): bool
    {
        $column ??= '_id';
        if ($column == '_id' && ! $this->query->connection->allowIdSort) {
            return $this->chunkByPit($count, $callback);
        }

        return parent::orderedChunkById($count, $callback, $column, $alias, $descending);
    }

    /**
     * @throws BuilderException
     */
    public function chunkByPit($count, callable $callback, $keepAlive = '1m'): bool
    {
        $this->query->keepAlive = $keepAlive;
        $pitId = $this->query->openPit();

        $searchAfter = null;
        $page = 1;
        do {
            $clone = clone $this;
            $clone->query->viaPit($pitId, $searchAfter);
            $results = $clone->getPit();
            $searchAfter = $results->getAfterKey();
            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return true;
            }

            unset($results);

            $page++;
        } while ($countResults == $count);

        $this->query->closePit($pitId);

        return true;
    }

    /**
     *  Using Laravel base method name rather
     *
     * @throws BindingResolutionException
     * @throws RuntimeException
     */
    public function cursorPaginate($perPage = null, $columns = ['*'], $cursorName = 'cursor', $cursor = null): SearchAfterPaginator
    {
        if ($perPage < 2) {
            throw new RuntimeException('Cursor pagination requires a perPage value greater than 1');
        }
        if (! $cursor instanceof Cursor) {
            $cursor = is_string($cursor) ? Cursor::fromEncoded($cursor) : CursorPaginator::resolveCurrentCursor('cursor', $cursor);
        }
        $this->query->limit($perPage);
        $this->query->initCursorMeta($cursor);
        $cursorMeta = $this->processCursorPaginator($perPage);
        $search = $this->get($columns);
        $searchAfter = $search->getAfterKey();

        return $this->searchAfterPaginator($search, $perPage, $cursor, [
            'path' => Paginator::resolveCurrentPath(),
            'cursorName' => $cursorName,
            'cursorMeta' => $cursorMeta,
            'searchAfter' => $searchAfter,
        ]);
    }

    protected function processCursorPaginator($perPage): array
    {
        $cursor = $this->query->getCursorMeta();
        $age = time() - $cursor['ts'];
        $ttl = 300; // 5 minutes
        $expired = $age > $ttl;
        if (! $cursor['pit_id'] || $expired) {
            $cursor['pit_id'] = $this->query->openPit();
            $clone = $this->clone();
            $cursor['records'] = $clone->count();
            $cursor['pages'] = (int) ceil($cursor['records'] / $perPage);
        }
        $cursor['ts'] = time();
        if ($cursor['next_sort'] && ! in_array($cursor['next_sort'], $cursor['sort_history'])) {
            $cursor['sort_history'][] = $cursor['next_sort'];
            $this->query->searchAfter($cursor['next_sort']);
        }
        $this->query->withPitId($cursor['pit_id']);
        $this->query->setCursorMeta($cursor);

        return $cursor;
    }

    /**
     * @throws BindingResolutionException
     */
    protected function searchAfterPaginator($items, $perPage, $cursor, $options)
    {
        return Container::getInstance()->makeWith(SearchAfterPaginator::class, compact('items', 'perPage', 'cursor', 'options'));
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
        $model['id'] = $id; // set the id to the model

        return $model;
    }

    public function withoutRefresh()
    {
        $this->model->options()->add('refresh', false);

        return $this->model;
    }

    /**
     * @throws DynamicIndexException
     */
    public function withSuffix($suffix)
    {
        try {
            $this->model->setSuffix($suffix);

            return $this->model;
        }

        // DynamicIndex trait required
        catch (Exception $e) {
            throw new DynamicIndexException('withSuffix() requires Dynamic Index trait', $this->model, $e);
        }
    }

    public function withTable($table)
    {
        $this->model->setTable($table);
        $this->query->from = $table;

        return $this;
    }

    // ----------------------------------------------------------------------
    // Aggregations
    // ----------------------------------------------------------------------

    /**
     *  Distinct executes Nested Term Aggs on the specified column(s)
     */
    public function distinct(mixed $columns = [], bool $includeCount = false): ElasticCollection
    {
        $elasticQueryCollection = $this->query->distinct($columns, $includeCount);
        $eloquentCollection = $this->model->hydrate(
            $elasticQueryCollection->all()
        );

        return ElasticCollection::loadCollection($eloquentCollection)->loadMeta($elasticQueryCollection->getQueryMeta());
    }

    public function min($column, array $options = [])
    {
        return $this->hydrateAggregationResult($this->query->min($column, $options));
    }

    public function max($column, array $options = [])
    {
        return $this->hydrateAggregationResult($this->query->max($column, $options));
    }

    public function sum($column, array $options = [])
    {
        return $this->hydrateAggregationResult($this->query->sum($column, $options));
    }

    public function avg($column, array $options = [])
    {
        return $this->hydrateAggregationResult($this->query->avg($column, $options));
    }

    public function aggregate($function, $columns = ['*'], $options = [])
    {
        return $this->hydrateAggregationResult($this->query->aggregate($function, $columns, $options));
    }

    public function getAggregationResults()
    {
        return $this->hydrateAggregationResult($this->query->getAggregationResults());
    }

    // ES Metric Aggregations

    public function boxplot($columns, $options = [])
    {
        return $this->hydrateAggregationResult($this->query->boxplot($columns, $options));
    }

    public function cardinality($columns, $options = [])
    {
        return $this->hydrateAggregationResult($this->query->cardinality($columns, $options));
    }

    public function extendedStats($columns, $options = [])
    {
        return $this->hydrateAggregationResult($this->query->extendedStats($columns, $options));
    }

    public function matrix($columns, $options = [])
    {
        return $this->hydrateAggregationResult($this->query->matrix($columns, $options));
    }

    public function medianAbsoluteDeviation($columns, $options = [])
    {
        return $this->hydrateAggregationResult($this->query->medianAbsoluteDeviation($columns, $options));
    }

    public function percentiles($columns, $options = [])
    {
        return $this->hydrateAggregationResult($this->query->percentiles($columns, $options));
    }

    public function stats($columns, $options = [])
    {
        return $this->hydrateAggregationResult($this->query->stats($columns, $options));
    }

    public function stringStats($columns, $options = [])
    {
        return $this->hydrateAggregationResult($this->query->stringStats($columns, $options));
    }

    public function agg(array $functions, string $column, array $options = [])
    {
        return $this->hydrateAggregationResult($this->query->agg($functions, $column, $options));
    }

    protected function hydrateAggregationResult($result)
    {
        if ($result instanceof Collection) {
            $items = $result->all();
            // If the first item is a key-value pair, return as is
            if (! isset($items[0])) {
                return $items;
            }
            $meta = $items[0]['_meta'] ?? new MetaDTO([]);
            $meta->set('_index', $this->query->inferIndex());
            $meta->set('query', 'aggregation');
            $meta->set('dsl', $this->query->toSql());
            $models = $this->model->hydrate(
                $items
            );

            return ElasticCollection::loadCollection($models)->setQueryMeta($meta);
        }

        return $result;
    }

    // ----------------------------------------------------------------------
    // Schema operations
    // ----------------------------------------------------------------------

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

    public function getIndexMappings(bool $raw = false): array
    {
        return Schema::on($this->query->connection->getName())->getMappings($this->from, $raw);
    }

    public function getFieldMappings(bool $raw = false): array
    {
        return Schema::connection($this->query->connection->getName())->getFieldMapping($this->from, '*', $raw);
    }

    public function getFieldMapping(string|array $field = '*', bool $raw = false): array
    {
        return Schema::connection($this->query->connection->getName())->getFieldMapping($this->from, $field, $raw);
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

    // ----------------------------------------------------------------------
    // Raw methods
    // ----------------------------------------------------------------------

    public function rawSearch($dslBody, $options = [])
    {
        $dsl = [
            'index' => $this->query->inferIndex(),
            'body' => $dslBody,
            ...$options,
        ];

        $items = $this->query->processedRaw($dsl);

        return $this->hydrate($items);
    }

    public function rawAggregation($dslBody, $options = []): array
    {
        $dsl = [
            'index' => $this->query->inferIndex(),
            'body' => $dslBody,
            ...$options,
        ];

        $results = $this->query->raw($dsl)->asArray();

        return $results['aggregations'] ?? [];
    }

    public function rawDsl($dsl): array
    {
        return $this->query->raw($dsl)->asArray();
    }

    // ----------------------------------------------------------------------
    // Protected
    // ----------------------------------------------------------------------

    protected function loadRelations($models, $builder)
    {
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $models;
    }

    // ----------------------------------------------------------------------
    // V4 Backwards Compatibility
    // ----------------------------------------------------------------------

    /**
     * @deprecated v5.0.0
     * @see withoutRefresh()
     */
    public function saveWithoutRefresh()
    {
        return $this->withoutRefresh()->save();
    }

    /**
     * @deprecated v5.0.0
     * @see withoutRefresh()
     */
    public function createWithoutRefresh($attributes = [])
    {
        return $this->withoutRefresh()->create($attributes);
    }

    /**
     * @deprecated v5.0.0
     * @see withoutRefresh()
     */
    public function firstOrCreateWithoutRefresh($attributes = [])
    {
        return $this->withoutRefresh()->firstOrCreate($attributes);
    }
}

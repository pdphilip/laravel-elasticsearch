<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as BaseEloquentBuilder;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use PDPhilip\Elasticsearch\Exceptions\MissingOrderException;
use PDPhilip\Elasticsearch\Helpers\QueriesRelationships;
use PDPhilip\Elasticsearch\Pagination\SearchAfterPaginator;
use PDPhilip\Elasticsearch\Query\Builder as QueryBuilder;
use RuntimeException;

/**
 * @property QueryBuilder $query
 * @property Model $model
 *
 * @template TModel of Model
 */
class Builder extends BaseEloquentBuilder
{
    use QueriesRelationships;

    /**
     * The methods that should be returned from query builder.
     *
     * @var array<string>
     */
    protected $passthru = [
        'aggregate',
        'average',
        'avg',
        'count',
        'dd',
        'doesntexist',
        'dump',
        'exists',
        'getbindings',
        'getconnection',
        'getgrammar',
        'insert',
        'insertgetid',
        'insertorignore',
        'insertusing',
        'max',
        'min',
        'pluck',
        'pull',
        'push',
        'raw',
        'sum',
        'tosql',
        //ES only:
        'matrix',
        'query',
        'rawsearch',
        'rawaggregation',
        'getindexsettings',
        'getindexmappings',
        'deleteindexifexists',
        'deleteindex',
        'truncate',
        'indexexists',
        'createindex',
        'search',
        'todsl',
        'agg',
    ];

    /**
     * @inerhitDoc
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->query->getConnection();
    }

    /**
     * Override the default getModels
     *
     * @return array<string, array>
     *
     * @phpstan-ignore-next-line
     */
    public function getModels($columns = ['*']): array
    {
        $data = $this->query->get($columns);
        $results = $this->model->hydrate($data->all())->all();

        return ['results' => $results];
    }

    /**
     * @inerhitDoc
     */
    public function get($columns = ['*']): Collection
    {
        $builder = $this->applyScopes();
        $fetch = $builder->getModels($columns);
        if (count($models = $fetch['results']) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $builder->getModel()->newCollection($models);

    }

    /**
     * Hydrate the models from the given array.
     *
     *
     * @return Collection<int, TModel>
     */
    public function hydrate(array $items): Collection
    {
        $instance = $this->newModelInstance();

        return $instance->newCollection(array_map(function ($item) use ($items, $instance) {
            $recordIndex = null;
            if (is_array($item)) {
                $recordIndex = ! empty($item['_index']) ? $item['_index'] : null;
                if ($recordIndex) {
                    unset($item['_index']);
                }
            }
            $meta = [];
            if (isset($item['_meta'])) {
                $meta = $item['_meta'];
                unset($item['_meta']);
            }
            $model = $instance->newFromBuilder($item);
            if ($recordIndex) {
                $model->setRecordIndex($recordIndex);
                $model->setIndex($recordIndex);

            }
            if ($meta) {
                $model->setMeta($meta);
            }
            if (count($items) > 1) {
                $model->preventsLazyLoading = Model::preventsLazyLoading();
            }

            return $model;
        }, $items));
    }

    /**
     * @see getModels($columns = ['*'])
     */
    public function searchModels($columns = ['*']): array
    {

        $data = $this->query->search($columns);
        $results = $this->model->hydrate($data->all())->all();

        return ['results' => $results];

    }

    /**
     * @see get($columns = ['*'])
     */
    public function search($columns = ['*']): Collection
    {
        $builder = $this->applyScopes();
        $fetch = $builder->searchModels($columns);
        if (count($models = $fetch['results']) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $builder->getModel()->newCollection($models);
    }

    public function firstOrCreate(array $attributes = [], array $values = []): Model
    {
        $instance = $this->_instanceBuilder($attributes);
        if (! is_null($instance)) {
            return $instance;
        }

        return $this->create(array_merge($attributes, $values));
    }

    private function _instanceBuilder(array $attributes = [])
    {
        $instance = clone $this;

        foreach ($attributes as $field => $value) {
            $method = is_string($value) ? 'whereExact' : 'where';

            if (is_array($value)) {
                foreach ($value as $v) {
                    $specificMethod = is_string($v) ? 'whereExact' : 'where';
                    $instance = $instance->$specificMethod($field, $v);
                }
            } else {
                $instance = $instance->$method($field, $value);
            }
        }

        return $instance->first();
    }

    public function updateWithoutRefresh(array $attributes = []): int
    {
        $query = $this->toBase();
        $query->setRefresh(false);

        return $query->update($this->addUpdatedAtColumn($attributes));
    }

    protected function addUpdatedAtColumn(array $values): array
    {
        if (! $this->model->usesTimestamps() || $this->model->getUpdatedAtColumn() === null) {
            return $values;
        }

        $column = $this->model->getUpdatedAtColumn();

        return array_merge([$column => $this->model->freshTimestampString()], $values);
    }

    public function firstOrCreateWithoutRefresh(array $attributes = [], array $values = [])
    {
        $instance = $this->_instanceBuilder($attributes);
        if (! is_null($instance)) {
            return $instance;
        }

        return $this->createWithoutRefresh(array_merge($attributes, $values));
    }

    /**
     * Fast create method for 'write and forget'
     */
    public function createWithoutRefresh(array $attributes = []): \Illuminate\Database\Eloquent\Model|\Illuminate\Support\HigherOrderTapProxy|null|Builder
    {
        return tap($this->newModelInstance($attributes), function ($instance) {
            $instance->saveWithoutRefresh();
        });
    }

    //----------------------------------------------------------------------
    // ES Filters
    //----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function chunkById(mixed $count, callable $callback, mixed $column = '_id', mixed $alias = null, string $keepAlive = '5m'): bool
    {
        $column ??= $this->defaultKeyName();
        $alias ??= $column;
        //remove sort
        $this->query->orders = [];

        if ($column === '_id') {
            //Use PIT
            return $this->_chunkByPit($count, $callback, $keepAlive);
        } else {
            $lastId = null;
            $page = 1;
            do {
                $clone = clone $this;
                $results = $clone->forPageAfterId($count, $lastId, $column)->get();
                $countResults = $results->count();
                if ($countResults == 0) {
                    break;
                }
                // @phpstan-ignore-next-line
                if ($callback($results, $page) === false) {
                    return true;
                }
                $aliasClean = $alias;
                if (str_ends_with($aliasClean, '.keyword')) {
                    $aliasClean = substr($aliasClean, 0, -8);
                }
                $lastId = data_get($results->last(), $aliasClean);

                if ($lastId === null) {
                    throw new RuntimeException("The chunkById operation was aborted because the [{$aliasClean}] column is not present in the query result.");
                }

                unset($results);

                $page++;
            } while ($countResults == $count);
        }

        return true;

    }

    private function _chunkByPit(mixed $count, callable $callback, string $keepAlive = '5m'): bool
    {
        $pitId = $this->query->openPit($keepAlive);

        $searchAfter = null;
        $page = 1;
        do {
            $clone = clone $this;
            $search = $clone->query->pitFind($count, $pitId, $searchAfter, $keepAlive);
            $meta = $search->getMetaData();
            $searchAfter = $meta['last_sort'];
            $results = $this->hydrate($search->data);
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

    //----------------------------------------------------------------------
    // ES Search query builders
    //----------------------------------------------------------------------

    public function chunk(mixed $count, callable $callback, string $keepAlive = '5m'): bool
    {
        //default to using PIT
        return $this->_chunkByPit($count, $callback, $keepAlive);
    }

    public function filterGeoBox(string $field, array $topLeft, array $bottomRight): self
    {
        $this->query->filterGeoBox($field, $topLeft, $bottomRight);

        return $this;
    }

    public function filterGeoPoint(string $field, string $distance, array $geoPoint): self
    {
        $this->query->filterGeoPoint($field, $distance, $geoPoint);

        return $this;
    }

    public function term(string $term, ?int $boostFactor = null): self
    {
        $this->query->searchQuery($term, $boostFactor);

        return $this;
    }

    public function andTerm(string $term, ?int $boostFactor = null): self
    {
        $this->query->searchQuery($term, $boostFactor, 'AND');

        return $this;
    }

    public function orTerm(string $term, ?int $boostFactor = null): self
    {
        $this->query->searchQuery($term, $boostFactor, 'OR');

        return $this;
    }

    public function fuzzyTerm(string $term, ?int $boostFactor = null): self
    {
        $this->query->searchQuery($term, $boostFactor, null, 'fuzzy');

        return $this;
    }

    public function andFuzzyTerm(string $term, ?int $boostFactor = null): self
    {
        $this->query->searchQuery($term, $boostFactor, 'AND', 'fuzzy');

        return $this;
    }

    public function orFuzzyTerm(string $term, ?int $boostFactor = null): self
    {
        $this->query->searchQuery($term, $boostFactor, 'OR', 'fuzzy');

        return $this;
    }

    public function regEx(string $regEx, ?int $boostFactor = null): self
    {
        $this->query->searchQuery($regEx, $boostFactor, null, 'regex');

        return $this;
    }

    public function andRegEx(string $regEx, ?int $boostFactor = null): self
    {
        $this->query->searchQuery($regEx, $boostFactor, 'AND', 'regex');

        return $this;
    }

    public function orRegEx(string $regEx, ?int $boostFactor = null): self
    {
        $this->query->searchQuery($regEx, $boostFactor, 'OR', 'regex');

        return $this;
    }

    public function phrase(string $term, ?int $boostFactor = null): self
    {
        $this->query->searchQuery($term, $boostFactor, null, 'phrase');

        return $this;
    }

    public function andPhrase(string $term, ?int $boostFactor = null): self
    {
        $this->query->searchQuery($term, $boostFactor, 'AND', 'phrase');

        return $this;
    }

    public function orPhrase(string $term, ?int $boostFactor = null): self
    {
        $this->query->searchQuery($term, $boostFactor, 'OR', 'phrase');

        return $this;
    }

    public function minShouldMatch($value): self
    {
        $this->query->minShouldMatch($value);

        return $this;
    }

    public function minScore(float $value): self
    {
        $this->query->minScore($value);

        return $this;
    }

    // Elastic type paginator that uses the search_after instead of limiting to Max results.

    public function field(string $field, ?int $boostFactor = null): self
    {
        $this->query->searchField($field, $boostFactor);

        return $this;
    }

    public function fields(array $fields): self
    {
        $this->query->searchFields($fields);

        return $this;
    }

    //----------------------------------------------------------------------
    // Inherited as is but typed
    //----------------------------------------------------------------------
    /**
     * Create a new instance of the model being queried.
     *
     * @param  array  $attributes
     */
    public function newModelInstance($attributes = []): Model
    {
        return $this->model->newInstance($attributes)->setConnection(
            $this->query->getConnection()->getName()
        );
    }

    /**
     * Override the default schema builder.
     */
    public function toBase(): QueryBuilder
    {
        return $this->applyScopes()->getQuery();
    }

    public function create(array $attributes = []): Model
    {
        return tap($this->newModelInstance($attributes), function ($instance) {
            $instance->save();
        });
    }

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    //----------------------------------------------------------------------
    // Private methods
    //----------------------------------------------------------------------

    /**
     * @throws MissingOrderException
     */
    public function searchAfterPaginate($perPage = null, array|string $columns = [], $cursor = null)
    {

        if (empty($this->query->orders)) {
            throw new MissingOrderException;
        }

        if (! $cursor instanceof Cursor) {
            $cursor = is_string($cursor)
                ? Cursor::fromEncoded($cursor)
                : CursorPaginator::resolveCurrentCursor('cursor', $cursor);
        }
        $this->query->limit($perPage);
        $cursorPayload = $this->query->initCursor($cursor);
        $age = time() - $cursorPayload['ts'];
        $ttl = 300; //5 minutes
        if ($age > $ttl) {
            // cursor is older than 5m, let's refresh it
            $clone = $this->clone();
            $cursorPayload['records'] = $clone->count();
            $cursorPayload['pages'] = (int) ceil($cursorPayload['records'] / $perPage);
            $cursorPayload['ts'] = time();
        }
        if ($cursorPayload['next_sort'] && ! in_array($cursorPayload['next_sort'], $cursorPayload['sort_history'])) {
            $cursorPayload['sort_history'][] = $cursorPayload['next_sort'];
        }
        $this->query->cursor = $cursorPayload;
        $search = $this->get($columns);

        return $this->searchAfterPaginator($search, $perPage, $cursor, [
            'path' => Paginator::resolveCurrentPath(),
            'cursorName' => 'cursor',
            'records' => $cursorPayload['records'],
            'totalPages' => $cursorPayload['pages'],
            'currentPage' => $cursorPayload['page'],
        ]);

    }

    /**
     * @throws BindingResolutionException
     */
    protected function searchAfterPaginator($items, $perPage, $cursor, $options)
    {
        return Container::getInstance()->makeWith(SearchAfterPaginator::class, compact(
            'items', 'perPage', 'cursor', 'options'
        ));
    }
}

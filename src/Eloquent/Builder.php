<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Database\Eloquent\Builder as BaseEloquentBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use PDPhilip\Elasticsearch\Collection\ElasticCollection;
use PDPhilip\Elasticsearch\Helpers\QueriesRelationships;
use PDPhilip\Elasticsearch\Query\Builder as QueryBuilder;

/**
 * @property QueryBuilder $query
 * @property Model $model
 *
 * @template TModel of Model
 * @template TCollection of ElasticCollection
 */
class Builder extends BaseEloquentBuilder
{
    use QueriesRelationships;

    protected $type;

    /**
     * Set a model instance for the model being queried.
     *
     * @param  Model  $model
     * @return $this
     */
    public function setModel($model): static
    {
        $this->model = $model;

        $this->query->from($model->getSearchIndex());

        $this->query->suffix($model->getSuffix());

        $this->query->type($model->getSearchType());

        return $this;
    }

    public function withSuffix($suffix): self
    {
        $this->query->suffix = $suffix;

        return $this;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
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

    public function getAggregations(string $collectionClass = ''): Collection
    {
        $collectionClass = $collectionClass ?: Collection::class;
        $aggregations = $this->query->getAggregationResults();

        return new $collectionClass($aggregations);
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
     * Get a generator for the given query.
     *
     * @return Generator
     */
    public function cursor()
    {
        foreach ($this->applyScopes()->query->cursor() as $record) {
            yield $this->model->newFromBuilder($record);
        }
    }

    /**
     * Paginate the given query.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     *
     * @throws \InvalidArgumentException
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $results = $this->forPage($page, $perPage)->get($columns);

        $total = $this->toBase()->getCountForPagination($columns);

        return new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }
}

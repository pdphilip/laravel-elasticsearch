<?php

namespace PDPhilip\Elasticsearch\Eloquent;

use Illuminate\Database\Eloquent\Builder as BaseEloquentBuilder;
use PDPhilip\Elasticsearch\Helpers\QueriesRelationships;

class Builder extends BaseEloquentBuilder
{
    use QueriesRelationships;

    /**
     * The methods that should be returned from query builder.
     *
     * @var array
     */
    protected $passthru = [
        'average',
        'avg',
        'count',
        'dd',
        'doesntExist',
        'dump',
        'exists',
        'getBindings',
        'getConnection',
        'getGrammar',
        'insert',
        'insertGetId',
        'insertOrIgnore',
        'insertUsing',
        'max',
        'min',
        'pluck',
        'pull',
        'push',
        'raw',
        'sum',
        'toSql',
        //ES only:
        'matrix',
        'query',
        'rawSearch',
        'getIndexSettings',
        'getIndexMappings',
        'deleteIndexIfExists',
        'deleteIndex',
        'truncate',
        'indexExists',
        'createIndex',
    ];


    /**
     * @inheritdoc
     */
    public function chunkById($count, callable $callback, $column = '_id', $alias = null)
    {
        return parent::chunkById($count, $callback, $column, $alias);
    }


    /**
     * @inheritDoc
     */
    public function getConnection()
    {
        return $this->query->getConnection();
    }

    /**
     * @inerhitDoc
     */
    public function getModels($columns = ['*'])
    {

        $data = $this->query->get($columns);
        $results = $this->model->hydrate($data->all())->all();

        return ['results' => $results];

    }

    /**
     * @inerhitDoc
     */
    public function get($columns = ['*'])
    {
        $builder = $this->applyScopes();
        $fetch = $builder->getModels($columns);
        if (count($models = $fetch['results']) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $builder->getModel()->newCollection($models);

    }

    /**
     *
     * @param    array    $values
     *
     * @return array
     */
    protected function addUpdatedAtColumn(array $values)
    {
        if (!$this->model->usesTimestamps() || $this->model->getUpdatedAtColumn() === null) {
            return $values;
        }

        $column = $this->model->getUpdatedAtColumn();
        $values = array_merge([$column => $this->model->freshTimestampString()], $values);

        return $values;
    }

    public function createWithoutRefresh(array $attributes = [])
    {
        return tap($this->newModelInstance($attributes), function ($instance) {
            $instance->saveWithoutRefresh();
        });
    }

}

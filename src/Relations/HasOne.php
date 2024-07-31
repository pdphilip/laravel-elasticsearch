<?php

namespace PDPhilip\Elasticsearch\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasOne as BaseHasOne;

class HasOne extends BaseHasOne
{

    public function getForeignKeyName()
    {
        return $this->foreignKey;
    }

    public function getHasCompareKey()
    {
        return $this->getForeignKeyName();
    }

    /**
     * @inheritdoc
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $foreignKey = $this->getForeignKeyName();


        return $query->select($foreignKey)->where($foreignKey, 'exists', true);
    }


    protected function whereInMethod(EloquentModel $model, $key)
    {
        return 'whereIn';
    }
}

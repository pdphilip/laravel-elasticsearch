<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasOne as BaseHasOne;

class HasOne extends BaseHasOne
{
    public function getHasCompareKey(): string
    {
        return $this->getForeignKeyName();
    }

    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $foreignKey = $this->getForeignKeyName();

        //@phpstan-ignore-next-line
        return $query->select($foreignKey)->where($foreignKey, 'exists', true);
    }

    protected function whereInMethod(EloquentModel $model, $key): string
    {
        return 'whereIn';
    }
}

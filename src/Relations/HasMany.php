<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany as BaseHasMany;

class HasMany extends BaseHasMany
{
    /**
     * {@inheritdoc}
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $foreignKey = $this->getHasCompareKey();

        //@phpstan-ignore-next-line
        return $query->select($foreignKey)->where($foreignKey, 'exists', true);
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     */
    public function getHasCompareKey(): string
    {
        return $this->getForeignKeyName();
    }

    /**
     * Get the plain foreign key.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    protected function whereInMethod(EloquentModel $model, $key): string
    {
        return 'whereIn';
    }
}

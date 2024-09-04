<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo as BaseBelongsTo;

class BelongsTo extends BaseBelongsTo
{
    public function getHasCompareKey(): string
    {
        return $this->ownerKey;
    }

    /**
     * {@inheritdoc}
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where($this->ownerKey, '=', $this->parent->{$this->foreignKey});
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {

        $this->query->whereIn($this->ownerKey, $this->getEagerModelKeys($models));
    }

    /**
     * {@inheritdoc}
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
    {
        return $query;
    }

    protected function whereInMethod(EloquentModel $model, $key): string
    {
        return 'whereIn';
    }
}

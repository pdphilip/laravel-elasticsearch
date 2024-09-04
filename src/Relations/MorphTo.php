<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\MorphTo as BaseMorphTo;

class MorphTo extends BaseMorphTo
{
    /** {@inheritdoc} */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where($this->ownerKey, '=', $this->getForeignKeyFrom($this->parent));
        }
    }

    /** {@inheritdoc} */
    protected function getResultsByType($type): Collection
    {
        $instance = $this->createModelByType($type);

        $key = $instance->getKeyName();

        $query = $instance->newQuery();

        return $query->whereIn($key, $this->gatherKeysByType($type, $instance->getKeyType()))->get();
    }

    protected function whereInMethod(EloquentModel $model, $key): string
    {
        return 'whereIn';
    }
}

<?php

namespace PDPhilip\Elasticsearch\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as BaseBelongsToMany;
use Illuminate\Support\Arr;
use RuntimeException;

class BelongsToMany extends BaseBelongsToMany
{
    public function __construct(
        Builder $query,
        Model $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null
    ) {
        throw new RuntimeException("BelongsToMany relation is currently not supported for this package. You can create a model as a pivot table and use HasMany relations to that instead.");
    }
}

<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use PDPhilip\Elasticsearch\Exceptions\RuntimeException;

class BelongsToMany extends EloquentBelongsToMany
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
        parent::__construct($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
        throw new RuntimeException('BelongsToMany relation is currently not supported for this package. You can create a model as a pivot table and use HasMany relations to that instead.');
    }
}

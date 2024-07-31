<?php

namespace PDPhilip\Elasticsearch\Relations;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\MorphMany as BaseMorphMany;

class MorphMany extends BaseMorphMany
{
    protected function whereInMethod(EloquentModel $model, $key)
    {
        return 'whereIn';
    }
}

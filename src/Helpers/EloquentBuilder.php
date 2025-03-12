<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Helpers;

use Illuminate\Database\Eloquent\Builder;

class EloquentBuilder extends Builder
{
    use QueriesRelationships;
}

<?php

namespace PDPhilip\Elasticsearch\Laravel\v11\Schema;

use Closure;
use PDPhilip\Elasticsearch\Schema\Blueprint;

trait BuilderCompatibility
{
    /**
     * {@inheritDoc}
     */
    protected function createBlueprint($table, ?Closure $callback = null): Blueprint
    {
        return new Blueprint($table, $callback);
    }
}

<?php

namespace PDPhilip\Elasticsearch\Laravel\Compatibility\Schema;

use Closure;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Utils\Helpers;

trait BuilderCompatibility
{
    /** {@inheritDoc} */
    protected function createBlueprint($table, ?Closure $callback = null): Blueprint
    {
        return new Blueprint(...$this->blueprintArgs($table, $callback));
    }

    public function getTableListing($schema = null, $schemaQualified = true)
    {
        return array_column($this->getTables(), 'name');
    }

    private function blueprintArgs(string $table, ?Closure $callback): array
    {
        if (Helpers::getLaravelCompatabilityVersion() >= 12) {
            return [$this->connection, $table, $callback];
        }

        return [$table, $callback];
    }
}

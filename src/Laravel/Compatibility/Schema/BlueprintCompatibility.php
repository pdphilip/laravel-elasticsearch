<?php

namespace PDPhilip\Elasticsearch\Laravel\Compatibility\Schema;

use PDPhilip\Elasticsearch\Utils\Helpers;

trait BlueprintCompatibility
{
    public function getConnection()
    {
        return $this->connection ?? null;
    }

    /** @phpstan-ignore method.childParameterType */
    public function build($connection = null, $grammar = null): void
    {
        if (Helpers::getLaravelCompatabilityVersion() >= 12) {
            $connection = $this->connection;
            $grammar = $this->grammar;
        }

        foreach ($this->toDSL($connection, $grammar) as $statement) {
            if ($connection->pretending()) {
                return;
            }

            $statement($this, $connection);
        }
    }
}

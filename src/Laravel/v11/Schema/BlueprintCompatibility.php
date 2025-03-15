<?php

namespace PDPhilip\Elasticsearch\Laravel\v11\Schema;

use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Schema\Grammars\Grammar;

trait BlueprintCompatibility
{
    public function build(Connection|\Illuminate\Database\Connection $connection, Grammar|\Illuminate\Database\Schema\Grammars\Grammar $grammar): void
    {
        foreach ($this->toDSL($connection, $grammar) as $statement) {
            if ($connection->pretending()) {
                return;
            }

            $statement($this, $connection);
        }
    }
}

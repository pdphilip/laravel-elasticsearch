<?php

namespace PDPhilip\Elasticsearch\Laravel\v11\Schema;

use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Schema\Grammars\Grammar;

trait BlueprintCompatibility
{
    // @phpstan-ignore-next-line
    public function build(Connection|\Illuminate\Database\Connection|null $connection = null, Grammar|\Illuminate\Database\Schema\Grammars\Grammar|null $grammar = null): void
    {
        foreach ($this->toDSL($connection, $grammar) as $statement) {
            if ($connection->pretending()) {
                return;
            }

            $statement($this, $connection);
        }
    }
}

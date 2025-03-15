<?php

namespace PDPhilip\Elasticsearch\Laravel\v12\Schema;

trait BlueprintCompatibility
{
    public function getConnection()
    {
        return $this->connection;
    }

    public function build(): void
    {
        $connection = $this->connection;
        $grammar = $this->grammar;
        foreach ($this->toDSL($connection, $grammar) as $statement) {
            if ($connection->pretending()) {
                return;
            }

            $statement($this, $connection);
        }
    }
}

<?php

namespace PDPhilip\Elasticsearch\Query\DSL;

class DslFactory
{
    public static function indexOperation(string $index, mixed $id = null, array $options = []): array
    {
        $operation = array_merge(['_index' => $index], $options);

        if ($id !== null) {
            $operation['_id'] = $id;
        }

        return ['index' => $operation];
    }
}

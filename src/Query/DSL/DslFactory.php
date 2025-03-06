<?php

namespace PDPhilip\Elasticsearch\Query\DSL;

class DslFactory
{
    // ----------------------------------------------------------------------
    // Index
    // ----------------------------------------------------------------------

    public static function indexOperation(string $index, mixed $id = null, array $options = []): array
    {
        $operation = array_merge(['_index' => $index], $options);

        if ($id !== null) {
            $operation['_id'] = $id;
        }

        return ['index' => $operation];
    }

    // ----------------------------------------------------------------------
    // Query
    // ----------------------------------------------------------------------

    public static function matchAll(): array
    {
        return ['match_all' => (object) []];
    }

    public static function match(string $field, $value, array $options = []): array
    {
        return [
            'match' => [
                $field => array_merge(
                    ['query' => $value],
                    $options
                ),
            ],
        ];
    }

    public static function terms(string $field, array $values, array $options = []): array
    {
        return [
            'terms' => array_merge(
                [$field => array_values($values)],
                $options
            ),
        ];
    }

    public static function exists(string $field, array $options = []): array
    {
        return [
            'exists' => array_merge(
                ['field' => $field],
                $options
            ),
        ];
    }

    public static function wildcard(string $field, string $value, array $options = []): array
    {
        return [
            'wildcard' => [
                $field => array_merge(
                    ['value' => $value],
                    $options
                ),
            ],
        ];
    }

    public static function range(string $field, array $conditions, array $options = []): array
    {
        return [
            'range' => [
                $field => array_merge(
                    $conditions,
                    $options
                ),
            ],
        ];
    }

    public static function functionScore(array $query, string $functionType, array $options = []): array
    {
        return [
            'function_score' => array_merge(
                [
                    $functionType => ['query' => $query],
                ],
                $options
            ),
        ];
    }
}

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

    public static function matchPhrase(string $field, string $value, array $options = []): array
    {
        return [
            'match_phrase' => [
                $field => array_merge(
                    ['query' => $value],
                    $options
                ),
            ],
        ];
    }

    public static function matchPhrasePrefix(string $field, string $value, array $options = []): array
    {
        return [
            'match_phrase_prefix' => [
                $field => array_merge(
                    ['query' => $value],
                    $options
                ),
            ],
        ];
    }

    public static function fuzzy(string $field, string $value, array $options = []): array
    {
        return [
            'fuzzy' => [
                $field => array_merge(
                    ['value' => (string) $value],
                    $options
                ),
            ],
        ];
    }

    public static function term(string $field, $value, array $options = []): array
    {
        return [
            'term' => [
                $field => array_merge(
                    ['value' => (string) $value],
                    $options
                ),
            ],
        ];
    }

    public static function multiMatch(string $value, array $options = []): array
    {
        return [
            'multi_match' => array_merge(
                ['query' => $value],
                $options
            ),
        ];
    }

    public static function constantScore(array $filter, array $options = []): array
    {
        return [
            'constant_score' => array_merge(
                ['filter' => $filter],
                $options
            ),
        ];
    }

    public static function script(string $source, array $options = []): array
    {
        return [
            'script' => [
                'script' => array_merge(
                    ['source' => $source],
                    $options
                ),
            ],
        ];
    }

    public static function nested(string $path, array $query, array $options = []): array
    {
        return [
            'nested' => array_merge(
                [
                    'path' => $path,
                ],
                $query,
                $options
            ),
        ];
    }

    public static function innerNested(string $path, array $query, array $innerHits = []): array
    {
        return [
            'nested' => [
                'path' => $path,
                'query' => $query,
                'inner_hits' => $innerHits,
            ],
        ];
    }

    public static function regexp(string $field, string $value, array $options = []): array
    {
        return [
            'regexp' => [
                $field => array_merge(
                    ['value' => $value],
                    $options
                ),
            ],
        ];
    }

    public static function geoDistance(string $field, string $distance, array $coordinates, array $options = []): array
    {
        return [
            'geo_distance' => array_merge(
                [$field => $coordinates, 'distance' => $distance],
                $options
            ),
        ];
    }

    public static function geoBoundingBox(string $field, array $coordinates, array $options = []): array
    {
        return [
            'geo_bounding_box' => array_merge(
                [$field => $coordinates],
                $options
            ),
        ];
    }

    public static function parentId(string $type, string $id, array $options = []): array
    {
        return [
            'parent_id' => array_merge(
                [
                    'type' => $type,
                    'id' => $id,
                ],
                $options
            ),
        ];
    }

    public static function prefix(string $field, string $value, array $options = []): array
    {
        return [
            'prefix' => [
                $field => array_merge(
                    ['value' => $value],
                    $options
                ),
            ],
        ];
    }

    public static function functionScore(array $query, string $functionType, array $options = []): array
    {
        return [
            'function_score' => array_merge(
                [$functionType => ['query' => $query]],
                $options
            ),
        ];
    }

    // ----------------------------------------------------------------------
    // Aggregations
    // ----------------------------------------------------------------------

    public static function nestedTermsAggregation(
        string $fieldName,
        string $field,
        int $size,
        array $orders = [],
        array $metricAggs = [],
        array $subAggs = []
    ): array {
        $terms = [
            'terms' => [
                'field' => $field,
                'size' => $size,
            ],
        ];

        if (! empty($metricAggs)) {
            $terms['aggs'] = $metricAggs;
        }

        if (! empty($orders)) {
            $terms['terms']['order'] = $orders;
        }

        if (! empty($subAggs)) {
            if (! empty($terms['aggs'])) {
                $terms['aggs'] = array_merge($terms['aggs'], $subAggs);
            } else {
                $terms['aggs'] = $subAggs;
            }
        }

        return ["by_{$fieldName}" => $terms];
    }

    public static function applySortsToAggregations(array $aggregations, ?array $sorts = null): array
    {
        if (empty($sorts)) {
            return $aggregations;
        }

        $aggregationCollection = collect($aggregations);
        $flat = $aggregationCollection->dot();

        foreach ($sorts as $sort) {
            foreach ($sort as $field => $order) {
                $key = ($flat->search($field));
                if ($key) {
                    $sortKey = str_replace('field', 'order', $key);
                    $flat->put($sortKey, $order['order']);
                }
            }
        }

        return $flat->undot()->all();
    }

    public static function categorizeText(array $args, array $options = []): array
    {
        return [
            'categorize_text' => array_merge(
                $args,
                $options
            ),
        ];
    }

    public static function composite(array $sources, ?int $size = null, ?array $afterKey = null, array $options = []): array
    {
        $compositeOptions = ['sources' => $sources];

        if ($size) {
            $compositeOptions['size'] = $size;
        }

        if ($afterKey !== null) {
            $compositeOptions['after'] = $afterKey;
        }

        return [
            'composite' => array_merge(
                $compositeOptions,
                $options
            ),
        ];
    }

    public static function matrixStats(string $field, array $options = []): array
    {
        return [
            'matrix_stats' => array_merge(
                ['fields' => $field],
                $options
            ),
        ];
    }

    public static function metricAggregation(string $metric, string $field, array $options = []): array
    {
        return [
            $metric => array_merge(
                ['field' => $field],
                $options
            ),
        ];
    }

    public static function scriptMetricAggregation(string $metric, string $script, array $options = []): array
    {
        return [
            $metric => array_merge(
                ['script' => $script],
                $options
            ),
        ];
    }

    public static function dateHistogram(
        string $field,
        ?string $fixedInterval = null,
        ?string $calendarInterval = null,
        ?int $minDocCount = null,
        ?array $extendedBounds = null,
        array $options = []
    ): array {
        $params = ['field' => $field];

        if ($fixedInterval !== null) {
            $params['fixed_interval'] = $fixedInterval;
        }

        if ($calendarInterval !== null) {
            $params['calendar_interval'] = $calendarInterval;
        }

        if ($minDocCount !== null) {
            $params['min_doc_count'] = $minDocCount;
        }

        if ($extendedBounds !== null) {
            $params['extended_bounds'] = $extendedBounds;
        }

        return [
            'date_histogram' => array_merge(
                $params,
                $options
            ),
        ];
    }

    public static function dateRange(array $args, array $options = []): array
    {
        return [
            'date_range' => array_merge(
                $args,
                $options
            ),
        ];
    }

    public static function filterAggregation(array $filter, array $options = []): array
    {
        return [
            'filter' => ! empty($filter) ? $filter : self::matchAll(),
        ];
    }

    public static function missingAggregation(string $field, array $options = []): array
    {
        return [
            'missing' => array_merge(
                ['field' => $field],
                $options
            ),
        ];
    }

    public static function nestedAggregation(string $path, array $options = []): array
    {
        return [
            'nested' => array_merge(
                ['path' => $path],
                $options
            ),
        ];
    }

    public static function reverseNestedAggregation(array $options = []): array
    {
        return [
            'reverse_nested' => empty($options) ? new \stdClass : $options,
        ];
    }

    public static function termsAggregation(string $field, int $size, array $options = []): array
    {
        return [
            'terms' => array_merge(
                [
                    'field' => $field,
                    'size' => $size,
                ],
                $options
            ),
        ];
    }

    public static function filterTermsAggregationOptions(array $options): array
    {
        $allowedArgs = [
            'collect_mode',
            'exclude',
            'execution_hint',
            'include',
            'min_doc_count',
            'missing',
            'order',
            'script',
            'show_term_doc_count_error',
            'size',
        ];

        return array_intersect_key($options, array_flip($allowedArgs));
    }

    // ----------------------------------------------------------------------
    // Sorting
    // ----------------------------------------------------------------------

    public static function sortByShardDoc($dir = 'asc')
    {
        return [
            '_shard_doc' => [
                'order' => $dir,
            ],
        ];
    }

    // ----------------------------------------------------------------------
    // Applications
    // ----------------------------------------------------------------------

    public static function applyBoost(array $clause, $boostValue): array
    {
        $firstKey = key($clause);

        if ($firstKey === 'term') {
            $key = key($clause['term']);

            // If already structured with 'value' key
            if (is_array($clause['term'][$key]) && isset($clause['term'][$key]['value'])) {
                $clause['term'][$key]['boost'] = $boostValue;
            } else {
                // Convert simple term to structured form
                $clause['term'] = [
                    $key => [
                        'value' => $clause['term'][$key],
                        'boost' => $boostValue,
                    ],
                ];
            }
        } else {
            // For other query types, add boost directly
            $clause[$firstKey]['boost'] = $boostValue;
        }

        return $clause;
    }

    public static function applyInnerHits(array $clause, $options)
    {
        $firstKey = key($clause);

        $clause[$firstKey]['inner_hits'] = empty($options) || $options === true ? (object) [] : (array) $options;

        return $clause;
    }
}

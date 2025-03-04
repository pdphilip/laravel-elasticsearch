<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query;

use DateTime;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDPhilip\Elasticsearch\Exceptions\BuilderException;
use PDPhilip\Elasticsearch\Helpers\Helpers;
use PDPhilip\Elasticsearch\Query\DSL\QueryCompiler;

class Grammar extends BaseGrammar
{
    // ======================================================================
    // Create
    // ======================================================================

    /**
     * Compile the given values to an Elasticsearch insert statement
     *
     * @param  Builder  $query
     */
    public function compileInsert($query, array $values): array
    {
        $params = [];

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        foreach ($values as $doc) {
            $doc['id'] = $doc['_id'] ?? $doc['id'] ?? $query->connection->getGeneratedId();
            if (isset($doc['child_documents'])) {
                foreach ($doc['child_documents'] as $childDoc) {
                    $childIndex = [
                        '_index' => $query->getFrom(),
                        '_id' => $childDoc['id'],
                    ];
                    if ($doc['id']) {
                        $childIndex['parent'] = $doc['id'];
                    }
                    $params['body'][] = [
                        'index' => $childIndex,
                    ];

                    $params['body'][] = $childDoc['document'];
                }

                unset($doc['child_documents']);
            }

            $index = [
                '_index' => $query->getFrom(),
            ];
            if ($doc['id']) {
                $index['_id'] = $doc['id'];
            }

            if (isset($doc['_routing'])) {
                $index['routing'] = $doc['_routing'];
                unset($doc['_routing']);
            } elseif ($routing = $query->getRouting()) {
                $index['routing'] = $routing;
            }

            if ($parentId = $query->getParentId()) {
                $index['parent'] = $parentId;
            } elseif (isset($doc['_parent'])) {
                $index['parent'] = $doc['_parent'];
                unset($doc['_parent']);
            }

            // We don't want to save the ID as part of the doc.
            unset($doc['id'], $doc['_id']);

            $params['body'][] = ['index' => $index];

            foreach ($doc as &$property) {
                $property = $this->getStringValue($property);
            }

            $params['body'][] = $doc;
        }

        $params['refresh'] = $query->getOption('refresh', true);

        return $params;
    }

    // ======================================================================
    // Read
    // ======================================================================

    /**
     * Compile a select statement
     *
     * @param  Builder  $query
     *
     * @throws BuilderException
     */
    public function compileSelect($query): array
    {
        $compiled = $this->compileWheres($query);
        $params = [
            'index' => $query->getFrom(),
            'body' => [
                'query' => $compiled['query'],
            ],
        ];

        if ($compiled['filter']) {
            if (empty($params['body']['query']['bool'])) {
                $currentQuery = $params['body']['query'];
                unset($params['body']['query']);
                $params['body']['query']['bool']['must'] = $currentQuery;
            }
            $params['body']['query']['bool']['filter'] = $compiled['filter'];
        }

        if ($compiled['postFilter']) {
            $params['body']['post_filter'] = $compiled['postFilter'];
        }

        // Apply order, offset and limit
        if ($query->orders) {
            $params['body']['sort'] = $this->compileOrders($query, $query->orders);
        }

        // Apply order, offset and limit
        if ($query->highlight) {
            $params['body']['highlight'] = $this->compileHighlight($query, $query->highlight);
        }

        if ($query->offset) {
            $params['body']['from'] = $query->offset;
        }

        $params['body']['size'] = $query->getLimit();

        if (isset($query->columns)) {
            $params['_source'] = $query->columns;
        }
        if ($query->distinct) {
            if ($query->columns && $query->columns !== ['*'] || $query->metricsAggregations) {
                $fields = $query->columns ?? [];
                $aggs = [];
                foreach ($query->metricsAggregations as $aggregation) {
                    if (! in_array($aggregation['key'], $fields)) {
                        $fields[] = $aggregation['key'];
                    }
                    $agg = $this->compileAggregation($query, $aggregation);
                    $aggs[$aggregation['key']] = reset($agg);
                    $aggs[$aggregation['key']]['key'] = $aggregation['type'].'_'.$aggregation['key'];

                }
                $params['body']['aggs'] = $this->compileNestedTermAggregations($fields, $query, $aggs);
                $params['body']['size'] = $query->getSetLimit();
                unset($params['body']['sort']);
            } else {
                // else nothing to aggregate - just a normal query as all records will be distinct anyway
                $query->distinct = false;
            }
        } elseif ($query->bucketAggregations) {
            $sorts = ! empty($params['body']['sort']) ? ($params['body']['sort']) : null;
            if (! empty($params['body']['from'])) {
                $afterCount = $params['body']['from'];
                unset($params['body']['from']);
                // This of course could be a problem if the user is using from for pagination and they go deep
                // Leaving it for now
                $query->afterKey = $query->getGroupByAfterKey($afterCount);
            }
            $params['body']['aggs'] = $this->compileBucketAggregations($query, $sorts);
            // If we are aggregating we set the body size to 0 to save on processing time.
            $params['body']['size'] = 0;

        } elseif ($query->metricsAggregations) {
            $params['body']['aggs'] = $this->compileMetricAggregations($query);

            // If we are aggregating we set the body size to 0 to save on processing time.
            $params['body']['size'] = 0;
        }

        if (isset($params['body']['query']) && ! $params['body']['query']) {
            unset($params['body']['query']);
        }

        return $params;
    }

    /**
     * Compile where clauses for a query
     *
     * @param  Builder  $query
     */
    public function compileWheres($query): array
    {
        $compiled['query'] = $this->compileBaseQuery($query, $query->wheres);
        $compiled['filter'] = $this->compileClauses($query, $query->filters ?? []);
        $compiled['postFilter'] = $this->compileClauses($query, $query->postFilters ?? []);

        return $compiled;
    }

    protected function compileBaseQuery(Builder $builder, array $wheres = [])
    {
        $dslCompiler = new QueryCompiler;
        foreach ($wheres as $where) {
            $isOr = str_starts_with($where['boolean'], 'or');
            $isNot = str_ends_with($where['boolean'], 'not');

            // Adjust operator to lowercase
            if (isset($where['operator'])) {
                $where['operator'] = strtolower($where['operator']);

                if ($where['operator'] === '!=') {
                    $isNot = ! $isNot;
                    $where['operator'] = '=';
                }
            }
            if (! empty($where['not'])) {
                $isNot = true;
            }

            // Handle column names
            if (isset($where['column'])) {
                $where['column'] = (string) $where['column'];

                // Adjust the column name if necessary
                if ($where['column'] === 'id') {
                    $where['column'] = '_id';
                }

                // Remove table prefix from column if present
                if (Str::startsWith($where['column'], $builder->from.'.')) {
                    $where['column'] = Str::replaceFirst($builder->from.'.', '', $where['column']);
                }
            }
            $method = 'compileWhere'.$where['type'];
            $result = $this->{$method}($builder, $where);
            $dslCompiler->setResult($result, $isOr, $isNot);

        }

        return $dslCompiler->compileQuery();
    }

    /**
     * Compile general clauses for a query
     */
    protected function compileClauses(Builder $builder, array $clauses): array
    {
        // The wheres to compile.
        $wheres = $clauses ?: [];

        // We will add all compiled wheres to this array.
        $compiled = [];
        foreach ($wheres as $i => &$where) {
            // Adjust operator to lowercase
            if (isset($where['operator'])) {
                $where['operator'] = strtolower($where['operator']);
            }

            // Handle column names
            if (isset($where['column'])) {
                $where['column'] = (string) $where['column'];

                // Adjust the column name if necessary
                if ($where['column'] === 'id') {
                    $where['column'] = '_id';
                }

                // Remove table prefix from column if present
                if (Str::startsWith($where['column'], $builder->from.'.')) {
                    $where['column'] = Str::replaceFirst($builder->from.'.', '', $where['column']);
                }
            }

            // Adjust the 'boolean' value of the first where if necessary
            if (
                $i === 0 && count($wheres) > 1
                && str_starts_with($where['boolean'], 'and')
                && str_starts_with($wheres[$i + 1]['boolean'], 'or')
            ) {
                $where['boolean'] = 'or'.(str_ends_with($where['boolean'], ' not') ? ' not' : '');
            }

            // We use different methods to compile different wheres
            $method = 'compileWhere'.$where['type'];
            $result = $this->{$method}($builder, $where);

            // Determine the boolean operator
            if (str_ends_with($where['boolean'], 'not')) {
                $boolOperator = 'must_not';
            } elseif (str_starts_with($where['boolean'], 'or')) {
                $boolOperator = 'should';
            } else {
                $boolOperator = 'must';
            }

            // Merge the compiled where with the others
            if (! isset($compiled['bool'][$boolOperator])) {
                $compiled['bool'][$boolOperator] = [];
            }

            $compiled['bool'][$boolOperator][] = $result;
        }

        return $compiled;
    }

    // ----------------------------------------------------------------------
    // Where Compilers
    // ----------------------------------------------------------------------

    public function compileWhereMatchAll(): array
    {
        return ['match_all' => (object) []];
    }

    /**
     * Compile a general where clause
     */
    protected function compileWhereBasic(Builder $builder, array $where): array
    {
        $value = $this->getValueForWhere($builder, $where);

        $operatorsMap = [
            '>' => 'gt',
            '>=' => 'gte',
            '<' => 'lt',
            '<=' => 'lte',
        ];

        if (is_null($value) || $where['operator'] == 'exists') {
            $query = [
                'exists' => [
                    'field' => $where['column'],
                ],
            ];
        } elseif (in_array($where['operator'], ['like', 'not like'])) {
            $query = [
                'wildcard' => [
                    $this->getIndexableField($where['column'], $builder) => [
                        'value' => str_replace('%', '*', $value),
                        ...($where['options'] ?? []),
                    ],
                ],
            ];
        } elseif (in_array($where['operator'], array_keys($operatorsMap))) {
            $operator = $operatorsMap[$where['operator']];
            $query = [
                'range' => [
                    $this->getIndexableField($where['column'], $builder) => [
                        $operator => $value,
                        ...($where['options'] ?? []),

                    ],
                ],
            ];
        } else {
            $query = [
                'match' => [
                    $where['column'] => [
                        'query' => $value,
                        'operator' => 'and',
                        ...($where['options'] ?? []),
                    ],
                ],
            ];
        }

        $query = $this->applyOptionsToClause($query, $where);

        if (
            ! empty($where['not'])
            || ($where['operator'] == 'not like' && ! is_null($value))
            || ($where['operator'] == '<>' && ! is_null($value))
            || ($where['operator'] == '!=' && ! is_null($value))
            || ($where['operator'] == '=' && is_null($value))
            || ($where['operator'] == 'exists' && ! $value)
        ) {
            $query = [
                'bool' => [
                    'must_not' => [
                        $query,
                    ],
                ],
            ];
        }

        return $query;
    }

    /**
     * Compile a where not clause
     *
     * @param  array  $where
     */
    protected function compileWhereNot(Builder $builder, $where): array
    {
        return [
            'bool' => [
                'must_not' => [
                    $this->compileWheres($where['query'])['query'],
                ],
            ],
        ];
    }

    /**
     * Compile a not in clause
     */
    protected function compileWhereNotIn(Builder $builder, array $where): array
    {
        return $this->compileWhereIn($builder, $where, true);
    }

    /**
     * Compile an in clause
     */
    protected function compileWhereIn(Builder $builder, array $where, $not = false): array
    {
        $column = $this->getIndexableField($where['column'], $builder);
        $values = $this->getValueForWhere($builder, $where);

        $query = [
            'terms' => [
                $column => array_values($values),
                ...$where['options'],
            ],
        ];

        $query = $this->applyOptionsToClause($query, $where);

        if ($not) {
            $query = [
                'bool' => [
                    'must_not' => [
                        $query,
                    ],
                ],
            ];
        }

        return $query;
    }

    /**
     * Compile a not null clause
     */
    protected function compileWhereNotNull(Builder $builder, array $where): array
    {
        $where['operator'] = '!=';

        return $this->compileWhereBasic($builder, $where);
    }

    /**
     * Compile a null clause
     */
    protected function compileWhereNull(Builder $builder, array $where): array
    {
        $where['operator'] = '=';

        return $this->compileWhereBasic($builder, $where);
    }

    /**
     * Compile a date clause
     */
    protected function compileWhereDate(Builder $builder, array $where): array
    {
        if ($where['operator'] == '=') {
            $value = $this->getValueForWhere($builder, $where);

            $where['value'] = [$value, $value];

            return $this->compileWhereBetween($builder, $where);
        }

        return $this->compileWhereBasic($builder, $where);
    }

    /**
     * Compile a where between clause
     */
    protected function compileWhereBetween(Builder $builder, array $where): array
    {
        $column = $where['column'];
        $values = $this->getValueForWhere($builder, $where);

        return [
            'range' => [
                $column => [
                    'gte' => $values[0],
                    'lte' => $values[1],
                ],
                ...$where['options'],
            ],
        ];
    }

    /**
     * Compile where for function score
     */
    protected function compileWhereFunctionScore(Builder $builder, array $where): array
    {
        $cleanWhere = $where;

        $compiled = $this->compileWheres($where['query']);

        foreach ($compiled as $queryPart => $clauses) {
            $compiled[$queryPart] = array_map(function ($clause) use ($where) {
                if ($clause) {
                    $this->applyOptionsToClause($clause, $where);
                }

                return $clause;
            }, $clauses);
        }

        $compiled = array_filter($compiled);

        $query = [
            'function_score' => [
                $where['functionType'] => ['query' => $compiled['query']],
            ],
        ];

        return $query;
    }

    /**
     * Compile a where geo bounds clause
     */
    protected function compileWhereGeoBoundsIn(Builder $builder, array $where): array
    {
        $query = [
            'geo_bounding_box' => [
                $where['column'] => $where['bounds'],
            ],
        ];

        return $query;
    }

    /**
     * Compile a geo distance clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     */
    protected function compileWhereGeoDistance($builder, $where): array
    {
        $query = [
            'geo_distance' => [
                'distance' => $where['distance'],
                $where['column'] => $where['location'],
            ],
        ];

        return $query;
    }

    /**
     * Compile a nested clause
     */
    protected function compileWhereNested(Builder $builder, array $where): array
    {
        $compiled = $this->compileWheres($where['query']);

        foreach ($compiled as $queryPart => $clauses) {
            $compiled[$queryPart] = array_map(function ($clause) use ($where) {
                if ($clause) {
                    $this->applyOptionsToClause($clause, $where);
                }

                return $clause;
            }, $clauses);
        }

        $compiled = array_filter($compiled);

        return reset($compiled);
    }

    /**
     * Compile a where nested clause
     *
     * @param  array  $where
     */
    protected function compileWhereNestedObject(Builder $builder, $where): array
    {
        $wheres = $this->compileWheres($where['query']);

        $query = [
            'nested' => [
                'path' => $where['column'],
            ],
        ];

        $query['nested'] = array_merge($query['nested'], array_filter($wheres));

        $query = $this->applyOptionsToClause($query, $where);

        if (isset($where['operator']) && $where['operator'] === '!=') {
            $query = [
                'bool' => [
                    'must_not' => [
                        $query,
                    ],
                ],
            ];
        }

        return $query;
    }

    /**
     * Compile a parent clause
     */
    protected function compileWhereParent(Builder $builder, array $where): array
    {
        return $this->applyWhereRelationship($builder, $where, 'parent');
    }

    /**
     * @return array
     */
    protected function compileWhereParentId(Builder $builder, array $where)
    {
        return [
            'parent_id' => [
                'type' => $where['relationType'],
                'id' => $where['id'],
            ],
        ];
    }

    protected function compileWherePrefix(Builder $builder, array $where): array
    {
        $query = [
            'prefix' => [
                $this->getIndexableField($where['column'], $builder) => $where['value'],
            ],
        ];

        return $query;
    }

    protected function compileWhereRaw(Builder $builder, array $where): array
    {
        return $where['sql']; // Return the raw query as-is
    }

    /**
     * Compile a date clause
     */
    protected function compileWhereRegex(Builder $builder, array $where): array
    {

        $allowedArgs = [
            'flags',
            'case_insensitive',
            'max_determinized_states',
            'rewrite',
        ];

        $where['options'] = $this->getAllowedOptions($where['options'], $allowedArgs);

        return [
            'regexp' => [
                $this->getIndexableField($where['column'], $builder) => [
                    'value' => Helpers::escape($where['value']),
                    ...$where['options'],
                ],
            ],
        ];
    }

    /**
     * Compile a match_phrase clause
     */
    protected function compileWherePhrase(Builder $builder, array $where): array
    {
        return [
            'match_phrase' => [
                $where['column'] => [
                    'query' => $where['value'],
                    ...$where['options'],

                ],
            ],
        ];
    }

    /**
     * Compile a match_phrase clause
     */
    //    protected function compileWhereMatch(Builder $builder, array $where): array
    //    {
    //
    //        $allowedArgs = [
    //            'analyzer',
    //            'auto_generate_synonyms_phrase_query',
    //            'boost',
    //            'fuzziness',
    //            'max_expansions',
    //            'prefix_length',
    //            'fuzzy_transpositions',
    //            'fuzzy_rewrite',
    //            'lenient',
    //            'operator',
    //            'minimum_should_match',
    //            'zero_terms_query',
    //        ];
    //
    //        $where['options'] = $this->getAllowedOptions($where['options'], $allowedArgs);
    //
    //        return [
    //            'match' => [
    //                $where['column'] => [
    //                    ...$where['options'],
    //                    'query' => $where['value'],
    //                ],
    //            ],
    //        ];
    //    }

    /**
     * Compile a match_phrase clause
     */
    protected function compileWherePhrasePrefix(Builder $builder, array $where): array
    {

        return [
            'match_phrase_prefix' => [
                $where['column'] => [
                    'query' => $where['value'],
                    ...$where['options'],
                ],
            ],
        ];
    }

    /**
     * Compile a term fuzzy clause
     */
    protected function compileWhereTermFuzzy(Builder $builder, array $where): array
    {

        return [
            'fuzzy' => [
                $where['column'] => [
                    'value' => (string) $where['value'],
                    ...$where['options'],
                ],
            ],
        ];
    }

    /**
     * Compile a term clause
     */
    protected function compileWhereTerm(Builder $builder, array $where): array
    {

        return [
            'term' => [
                $this->getIndexableField($where['column'], $builder) => [
                    'value' => (string) $where['value'],
                    ...$where['options'],
                ],
            ],
        ];
    }

    /**
     * Compile a script clause
     */
    protected function compileWhereScript(Builder $builder, array $where): array
    {
        return [
            'script' => [
                'script' => array_merge($where['options'], ['source' => $where['script']]),
            ],
        ];
    }

    /**
     * Compile a search clause for Elasticsearch.
     */
    protected function compileWhereSearch(Builder $builder, array $where): array
    {

        $constantScore = false;
        if (isset($where['options']['constant_score'])) {
            $constantScore = $where['options']['constant_score'];
            unset($where['options']['constant_score']);
        }
        $query = [
            'multi_match' => [
                'query' => $where['value'],
                ...$where['options'],
            ],
        ];
        if ($constantScore) {
            return [
                'constant_score' => [
                    'filter' => $query,
                ],
            ];
        }

        return $query;
        //        // Determine the fields to search
        //        $fields = $where['options']['fields'] ?? '*'; // Use '*' for all fields if none are specified
        //
        //        // Handle field boosts if fields is an associative array
        //        if (is_array($fields) && array_keys($fields) !== range(0, count($fields) - 1)) {
        //            $fields = array_map(fn ($field, $boost) => "{$field}^{$boost}", array_keys($fields), $fields);
        //        }
        //
        //        // Use multi_match if searching across multiple fields or all fields
        //        if (is_array($fields) || $fields === '*') {
        //            $queryType = $where['options']['matchType'] ?? 'best_fields';
        //
        //            $query = [
        //                'multi_match' => [
        //                    'query' => $where['value'],
        //                    'fields' => is_array($fields) ? $fields : ['*'], // Use '*' for all fields
        //                    'type' => $queryType,
        //                ],
        //            ];
        //        } else {
        //            $query = [
        //                'match' => [
        //                    $fields => [
        //                        'query' => $where['value'],
        //                    ],
        //                ],
        //            ];
        //        }
        //
        //        // Add fuzziness if specified
        //        if (! empty($where['options']['fuzziness'])) {
        //            $fuzziness = $where['options']['fuzziness'];
        //            $mainQueryType = key($query);
        //
        //            if ($mainQueryType === 'multi_match') {
        //                $query['multi_match']['fuzziness'] = $fuzziness;
        //            } else {
        //                $query['match'][$fields]['fuzziness'] = $fuzziness;
        //            }
        //        }
        //
        //        // Wrap in constant_score if specified
        //        if (! empty($where['options']['constant_score'])) {
        //            $query = [
        //                'constant_score' => [
        //                    'filter' => $query,
        //                ],
        //            ];
        //        }
        //
        //        return $query;
    }

    /**
     * Compile a child clause
     */
    protected function compileWhereChild(Builder $builder, array $where): array
    {
        return $this->applyWhereRelationship($builder, $where, 'child');
    }

    /**
     * Compile a relationship clause
     */
    protected function applyWhereRelationship(Builder $builder, array $where, string $relationship): array
    {
        $compiled = $this->compileWheres($where['value']);

        $relationshipFilter = "has_{$relationship}";
        $type = $relationship === 'parent' ? 'parent_type' : 'type';

        // pass filter to query if empty allowing a filter interface to be used in relation query
        // otherwise match all in relation query
        if (empty($compiled['query'])) {
            $compiled['query'] = empty($compiled['filter']) ? ['match_all' => (object) []] : $compiled['filter'];
        } elseif (! empty($compiled['filter'])) {
            throw new InvalidArgumentException('Cannot use both filter and query contexts within a relation context');
        }

        $query = [
            $relationshipFilter => [
                $type => $where['documentType'],
                'query' => $compiled['query'],
            ],
        ];

        $query = $this->applyOptionsToClause($query, $where);

        return $query;
    }

    // ----------------------------------------------------------------------
    // Order and Highlight
    // ----------------------------------------------------------------------

    /**
     * Compile the orders section of a query
     *
     * @param  array  $orders
     *
     * @throws BuilderException
     */
    protected function compileOrders(Builder|BaseBuilder $builder, $orders = []): array
    {
        $compiledOrders = [];

        foreach ($orders as $order) {
            $column = $order['column'];
            if (Str::startsWith($column, $builder->from.'.')) {
                $column = Str::replaceFirst($builder->from.'.', '', $column);
            }

            $column = $this->getIndexableField($column, $builder);

            $type = $order['type'] ?? 'basic';

            switch ($type) {
                case 'geoDistance':
                    $orderSettings = [
                        $column => $order['options']['coordinates'],
                        'order' => $order['direction'] < 0 ? 'desc' : 'asc',
                        'unit' => $order['options']['unit'] ?? 'km',
                        'distance_type' => $order['options']['distance_type'] ?? 'arc',
                    ];

                    $column = '_geo_distance';
                    break;

                default:
                    $orderSettings = [
                        'order' => $order['direction'] < 0 ? 'desc' : 'asc',
                    ];

                    $allowedOptions = ['missing', 'mode', 'nested'];

                    $options = isset($order['options']) ? array_intersect_key($order['options'], array_flip($allowedOptions)) : [];

                    $orderSettings = array_merge($options, $orderSettings);
            }

            $compiledOrders[] = [
                $column => $orderSettings,
            ];
        }

        return $compiledOrders;
    }

    /**
     * Compile the highlight section of a query
     *
     * @param  array  $highlight
     */
    protected function compileHighlight(Builder $builder, mixed $highlight = []): array
    {
        $allowedArgs = [
            'boundary_chars',
            'boundary_max_scan',
            'boundary_scanner',
            'boundary_scanner_locale',
            'encoder',
            'fragmenter',
            'force_source',
            'fragment_offset',
            'fragment_size',
            'highlight_query',
            'matched_fields',
            'number_of_fragments',
        ];

        $compiledHighlights = $this->getAllowedOptions($highlight['options'], $allowedArgs);

        foreach ($highlight['column'] as $column => $value) {

            if (is_array($value)) {
                $compiledHighlights['fields'][] = [$column => $value];
            } elseif ($value != '*') {
                $compiledHighlights['fields'][] = [$value => (object) []];
            }

        }

        $compiledHighlights['pre_tags'] = $highlight['preTag'];
        $compiledHighlights['post_tags'] = $highlight['postTag'];

        return $compiledHighlights;
    }

    // ======================================================================
    // Aggregations
    // ======================================================================

    /**
     * @throws BuilderException
     */
    protected function compileNestedTermAggregations($fields, Builder $query, $aggs = []): array
    {
        $currentField = array_shift($fields);
        $field = $this->getIndexableField($currentField, $query);

        $terms = [
            'terms' => [
                'field' => $field,
                'size' => $query->getLimit(),
            ],
        ];

        if (! empty($aggs[$currentField])) {
            $key = $aggs[$currentField]['key'];
            $metricAgg = $aggs[$currentField];
            unset($metricAgg['key']);
            $terms['aggs'][$key] = $metricAgg;
        }

        // Sorting logic
        $sorts = $query->sorts;
        $orders = collect($query->orders);
        $termsOrders = [];

        if (isset($sorts['_count'])) {
            $termsOrders[] = $sorts['_count'] == 1 ? ['_count' => 'asc'] : ['_count' => 'desc'];
        }

        $fieldOrder = $orders->where('column', $currentField)->first();
        if ($fieldOrder) {
            $termsOrders[] = $fieldOrder['direction'] == 1 ? ['_key' => 'asc'] : ['_key' => 'desc'];
        }

        if (! empty($termsOrders)) {
            $terms['terms']['order'] = $termsOrders;
        }
        if (! empty($fields)) {
            $nestedAggs = $this->compileNestedTermAggregations($fields, $query, $aggs);
            if (! empty($terms['aggs'])) {
                $terms['aggs'] = array_merge($terms['aggs'], $nestedAggs);
            } else {
                $terms['aggs'] = $nestedAggs;
            }
        }

        return ["by_{$currentField}" => $terms];
    }

    /**
     * Compile all aggregations
     */
    protected function compileBucketAggregations(Builder $builder, $sorts = null): array
    {
        $aggregations = collect();

        $metricsAggregations = [];
        if ($builder->metricsAggregations) {
            $metricsAggregations = $this->compileMetricAggregations($builder);
        }

        foreach ($builder->bucketAggregations as $aggregation) {
            // This lets us dynamically set the metric aggregation inside the bucket
            if (! empty($metricsAggregations)) {

                $aggregation['aggregations'] = $builder->newQuery();

                // @phpstan-ignore-next-line
                $aggregation['aggregations']->metricsAggregations = $builder->metricsAggregations;
            }

            $result = $this->compileAggregation($builder, $aggregation);

            $aggregations = $aggregations->mergeRecursive($result);
        }
        if ($sorts) {
            $flat = $aggregations->dot();
            foreach ($sorts as $sort) {
                foreach ($sort as $field => $order) {
                    $key = ($flat->search($field));
                    if ($key) {
                        $sortKey = str_replace('field', 'order', $key);
                        $flat->put($sortKey, $order['order']);
                    }
                }

            }
            $aggregations = $flat->undot();
        }

        return $aggregations->all();
    }

    /**
     * Compile all aggregations
     */
    protected function compileMetricAggregations(Builder $builder): array
    {
        $aggregations = [];

        foreach ($builder->metricsAggregations as $aggregation) {
            $result = $this->compileAggregation($builder, $aggregation);

            $aggregations = array_merge_recursive($aggregations, $result);
        }

        return $aggregations;
    }

    /**
     * Compile a single aggregation
     */
    protected function compileAggregation(Builder $builder, array $aggregation): array
    {
        $key = $aggregation['key'];

        $method = 'compile'.ucfirst(Str::camel($aggregation['type'])).'Aggregation';
        $compiledPayload = $this->$method($builder, $aggregation);
        $compiled = [$key => $compiledPayload];

        if (isset($aggregation['aggregations']) && $aggregation['aggregations']->metricsAggregations) {
            $compiled[$key]['aggs'] = $this->compileMetricAggregations($aggregation['aggregations']);
        }

        return $compiled;
    }

    // ----------------------------------------------------------------------
    // Aggregation compilers
    // ----------------------------------------------------------------------

    /**
     * Compile sum aggregation
     */
    public function compileSumAggregation(Builder $builder, array $aggregation): array
    {
        return $this->compileMetricAggregation($builder, $aggregation);
    }

    /**
     * Apply a boost option to the clause
     *
     * @param  mixed  $value
     * @param  array  $where
     */
    protected function applyBoostOption(array $clause, $value, $where): array
    {
        $firstKey = key($clause);

        if ($firstKey !== 'term') {
            return $clause[$firstKey]['boost'] = $value;
        }

        $key = key($clause['term']);

        $clause['term'] = [
            $key => [
                'value' => $clause['term'][$key],
                'boost' => $value,
            ],
        ];

        return $clause;
    }

    /**
     * Compile avg aggregation
     */
    protected function compileAvgAggregation(Builder $builder, array $aggregation): array
    {
        return $this->compileMetricAggregation($builder, $aggregation);
    }

    /**
     * Compile cardinality aggregation
     */
    protected function compileCardinalityAggregation(Builder $builder, array $aggregation): array
    {
        return $this->compileMetricAggregation($builder, $aggregation);
    }

    /**
     * Compile children aggregation
     */
    protected function compileChildrenAggregation(Builder $builder, array $aggregation): array
    {
        $type = is_array($aggregation['args']) ? $aggregation['args']['type'] : $aggregation['args'];

        return [
            'children' => [
                'type' => $type,
            ],
        ];
    }

    /**
     * Compile categorize_text aggregation
     */
    protected function compileCategorizeTextAggregation(Builder $builder, array $aggregation): array
    {

        $options = $aggregation['options'] ?? [];

        return [
            'categorize_text' => [
                ...$aggregation['args'],
                ...$options,
            ],
        ];
    }

    /**
     * Compile composite aggregation
     */
    protected function compileCompositeAggregation(Builder $builder, array $aggregation): array
    {
        $size = $builder->getSetLimit();
        $afterKey = $builder->afterKey ?? null;

        $compiled = [
            'composite' => [
                'sources' => $aggregation['args'],
            ],
        ];

        if ($size) {
            $compiled['composite']['size'] = $size;
        }
        if ($afterKey) {
            $compiled['composite']['after'] = $afterKey;
        }

        return $compiled;
    }

    /**
     * Compile count aggregation
     */
    protected function compileCountAggregation(Builder $builder, array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];
        $aggregation = [];
        $aggregation['type'] = 'value_count';
        $aggregation['args']['field'] = $field;
        $aggregation['args']['script'] = "doc.containsKey('{$field}') && !doc['{$field}'].empty ? 1 : 0";

        return $this->compileMetricAggregation($builder, $aggregation);
    }

    /**
     * Compile count aggregation
     */
    protected function compileValueCountAggregation(Builder $builder, array $aggregation): array
    {
        return $this->compileMetricAggregation($builder, $aggregation);
    }

    /**
     * Compile stats aggregation
     */
    protected function compileStatsAggregation(Builder $builder, array $aggregation): array
    {
        return $this->compileMetricAggregation($builder, $aggregation);
    }

    /**
     * Compile Extended stats aggregation
     */
    protected function compileExtendedStatsAggregation(Builder $builder, array $aggregation): array
    {
        return $this->compileMetricAggregation($builder, $aggregation);
    }

    /**
     * Compile percentiles aggregation
     */
    protected function compilePercentilesAggregation(Builder $builder, array $aggregation): array
    {
        return $this->compileMetricAggregation($builder, $aggregation);
    }

    /**
     * Compile string stats aggregation
     */
    protected function compileStringStatsAggregation(Builder $builder, array $aggregation): array
    {
        return $this->compileMetricAggregation($builder, $aggregation);
    }

    /**
     * Compile string stats aggregation
     */
    protected function compileMedianAbsoluteDeviationAggregation(Builder $builder, array $aggregation): array
    {
        return $this->compileMetricAggregation($builder, $aggregation);
    }

    /**
     * Compile boxplot aggregation
     */
    protected function compileBoxplotAggregation(Builder $builder, array $aggregation): array
    {
        return $this->compileMetricAggregation($builder, $aggregation);
    }

    /**
     * Compile matrix_stats aggregation
     */
    protected function compileMatrixStatsAggregation(Builder $builder, array $aggregation): array
    {

        $metric = $aggregation['type'];
        $options = $aggregation['options'] ?? [];

        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        return [
            $metric => [
                'fields' => $field,
                ...$options,
            ],
        ];
    }

    /**
     * Compile metric aggregation
     */
    protected function compileMetricAggregation(Builder $builder, array $aggregation): array
    {
        $metric = $aggregation['type'];
        $options = $aggregation['options'] ?? [];

        if (is_array($aggregation['args']) && isset($aggregation['args']['script'])) {
            return [
                $metric => [
                    'script' => $aggregation['args']['script'],
                ],
            ];
        }
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        return [
            $metric => [
                'field' => $field,
                ...$options,
            ],
        ];
    }

    /**
     * Compile date histogram aggregation
     */
    protected function compileDateHistogramAggregation(Builder $builder, array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        $compiled = [
            'date_histogram' => [
                'field' => $field,
            ],
        ];

        if (is_array($aggregation['args'])) {
            if (isset($aggregation['args']['fixed_interval'])) {
                $compiled['date_histogram']['fixed_interval'] = $aggregation['args']['fixed_interval'];
            }
            if (isset($aggregation['args']['calendar_interval'])) {
                $compiled['date_histogram']['calendar_interval'] = $aggregation['args']['calendar_interval'];
            }

            if (isset($aggregation['args']['min_doc_count'])) {
                $compiled['date_histogram']['min_doc_count'] = $aggregation['args']['min_doc_count'];
            }

            if (isset($aggregation['args']['extended_bounds']) && is_array($aggregation['args']['extended_bounds'])) {
                $compiled['date_histogram']['extended_bounds'] = [];
                $compiled['date_histogram']['extended_bounds']['min'] = $this->convertDateTime($aggregation['args']['extended_bounds'][0]);
                $compiled['date_histogram']['extended_bounds']['max'] = $this->convertDateTime($aggregation['args']['extended_bounds'][1]);
            }
        }

        return $compiled;
    }

    /**
     * Compile date range aggregation
     */
    protected function compileDateRangeAggregation(Builder $builder, array $aggregation): array
    {
        $compiled = [
            'date_range' => $aggregation['args'],
        ];

        return $compiled;
    }

    /**
     * Compile exists aggregation
     */
    protected function compileExistsAggregation(Builder $builder, array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        $compiled = [
            'exists' => [
                'field' => $field,
            ],
        ];

        return $compiled;
    }

    /**
     * Compile filter aggregation
     */
    protected function compileFilterAggregation(Builder $builder, array $aggregation): array
    {
        $filter = $this->compileWheres($aggregation['args']);

        $filters = $filter['filter'] ?? [];
        $query = $filter['query'] ?? [];

        $allFilters = array_merge($query, $filters);

        return [
            'filter' => $allFilters ?: ['match_all' => (object) []],
        ];
    }

    /**
     * Compile max aggregation
     */
    protected function compileMaxAggregation(Builder $builder, array $aggregation): array
    {
        return $this->compileMetricAggregation($builder, $aggregation);
    }

    /**
     * Compile min aggregation
     */
    protected function compileMinAggregation(Builder $builder, array $aggregation): array
    {
        return $this->compileMetricAggregation($builder, $aggregation);
    }

    /**
     * Compile missing aggregation
     */
    protected function compileMissingAggregation(Builder $builder, array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        $compiled = [
            'missing' => [
                'field' => $field,
            ],
        ];

        return $compiled;
    }

    /**
     * Compile nested aggregation
     */
    protected function compileNestedAggregation(Builder $builder, array $aggregation): array
    {
        $path = is_array($aggregation['args']) ? $aggregation['args']['path'] : $aggregation['args'];

        return [
            'nested' => [
                'path' => $path,
            ],
        ];
    }

    /**
     * Compile reverse nested aggregation
     */
    protected function compileReverseNestedAggregation(Builder $builder, array $aggregation): array
    {
        return [
            'reverse_nested' => (object) [],
        ];
    }

    /**
     * Compile terms aggregation
     */
    protected function compileTermsAggregation(Builder $builder, array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['key'];

        $compiled = [
            'terms' => [
                'field' => $this->getIndexableField($field, $builder),
                'size' => $builder->getLimit(),
            ],
        ];

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

        if (is_array($aggregation['args'])) {
            $validArgs = array_intersect_key($aggregation['args'], array_flip($allowedArgs));
            $compiled['terms'] = array_merge($compiled['terms'], $validArgs);
        }

        return $compiled;
    }

    /**
     * Apply inner hits options to the clause
     */
    protected function applyInnerHitsOption(array $clause, $options): array
    {
        $firstKey = key($clause);

        $clause[$firstKey]['inner_hits'] = empty($options) || $options === true ? (object) [] : (array) $options;

        return $clause;
    }

    /**
     * Get value for the where
     *
     * @return mixed
     */
    protected function getValueForWhere(Builder $builder, array $where)
    {
        switch ($where['type']) {
            case 'In':
            case 'NotIn':
            case 'Between':
                $value = $where['values'];
                break;

            case 'Null':
            case 'NotNull':
                $value = null;
                break;

            default:
                $value = $where['value'];
        }
        $value = $this->getStringValue($value);

        return $value;
    }

    /**
     * @return mixed
     */
    protected function getStringValue($value)
    {
        // Convert DateTime values to UTCDateTime.
        if ($value instanceof DateTime) {
            $value = $this->convertDateTime($value);
        } else {
            if (is_array($value)) {
                foreach ($value as &$val) {
                    if ($val instanceof DateTime) {
                        $val = $this->convertDateTime($val);
                    }
                }
            }

        }

        return $value;
    }

    /**
     * Compile a delete query
     */
    protected function convertDateTime($value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return $value->format('c');
    }

    /**
     * Apply the given options from a where to a query clause
     */
    protected function applyOptionsToClause(array $clause, array $where): array
    {
        if (empty($where['options'])) {
            return $clause;
        }

        $optionsToApply = ['inner_hits'];
        $options = array_intersect_key($where['options'], array_flip($optionsToApply));

        foreach ($options as $option => $value) {
            $method = 'apply'.Str::studly($option).'Option';

            if (method_exists($this, $method)) {
                $clause = $this->$method($clause, $value, $where);
            }
        }

        return $clause;
    }

    private function getAllowedOptions(array $options, array $allowed): array
    {
        return array_intersect_key($options, array_flip($allowed));
    }

    // ======================================================================
    // Update
    // ======================================================================

    /**
     * Compile a update query
     *
     *
     * @param  Builder  $query
     *
     * @throws BuilderException
     */
    public function compileUpdate($query, $values): array|string
    {
        $clause = $this->compileSelect($query);
        $clause['body']['conflicts'] = 'proceed';
        $scripts = [];
        $params = [];

        foreach ($values as $column => $value) {
            $value = $this->getStringValue($value);
            if (Str::startsWith($column, $query->from.'.')) {
                $column = Str::replaceFirst($query->from.'.', '', $column);
            }

            $param = str($column)->replace('.', '_')->toString();

            $params[$param] = $value;
            $scripts[] = 'ctx._source.'.$column.' = params.'.$param.';';
        }

        foreach ($query->scripts ?? [] as $script) {
            $params = [
                ...$params,
                ...$script['options']['params'],
            ];
            $scripts[] = $script['script'];
        }

        if (! empty($scripts)) {
            $clause['body']['script']['source'] = implode(' ', $scripts);
        }

        if (! empty($params)) {
            $clause['body']['script']['params'] = $params;
        }

        $clause['refresh'] = $query->options()->get('refresh', true);

        return $clause;
    }

    // ======================================================================
    // Delete
    // ======================================================================

    /**
     * Compile a delete query
     *
     * @param  Builder  $query
     *
     * @throws BuilderException
     */
    public function compileDelete($query): array
    {
        $clause = $this->compileSelect($query);

        $clause['refresh'] = $query->options()->get('refresh', true);
        $clause['conflicts'] = $query->options()->get('conflicts', 'abort');

        // If we don't have a query then we must be deleting everything IE truncate.
        if (! isset($clause['body']['query'])) {
            $clause['body']['query'] = ['match_all' => (object) []];
        }

        return $clause;
    }

    /**
     * Compile a delete query
     *
     * @param  Builder  $query
     *
     * @throws BuilderException
     */
    public function compileTruncate($query): array
    {
        $clause = $this->compileSelect($query);

        $clause['body'] = [
            'query' => [
                'match_all' => (object) [],
            ],
        ];

        $clause['refresh'] = $query->getOption('refresh', true);

        return $clause;
    }

    // ----------------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------------
    /**
     * Given a `$field` points to the subfield that is of type keyword.
     *
     *
     * @throws BuilderException
     */
    public function getIndexableField(string $textField, Builder $builder): string
    {

        // _id doesn't need to be found.
        if ($textField == '_id' || $textField == 'id') {
            return '_id';
        }

        // Checks if there is a mapping_map set for this field and return is ahead of a mapping check.
        if (! empty($mappingMap = $builder->options()->get('mapping_map')) && $mappingMap[$textField]) {
            return $mappingMap[$textField];
        }

        if ($builder->connection->options()->get('bypass_map_validation')) {
            return $textField;
        }
        $cacheKey = $builder->from.'_mapping_cache';
        $mapping = $builder->options()->get($cacheKey);
        if (! $mapping) {
            $mapping = collect(Arr::dot($builder->getMapping()));
            $builder->options()->add($cacheKey, $mapping);
        }
        $keywordKey = $mapping->keys()
            ->filter(fn ($field) => str_starts_with($field, $textField) && ! in_array($mapping[$field], ['text', 'binary']))
            ->first();

        if (! empty($keywordKey)) {

            return $keywordKey;
        }

        throw new BuilderException("{$textField} does not have a keyword field.");
    }

    /**
     * {@inheritdoc}
     */
    public function getDateFormat(): string
    {
        return Config::get('laravel-elasticsearch.date_format', 'Y-m-d H:i:s');
    }
    // ----------------------------------------------------------------------
    // Index
    // ----------------------------------------------------------------------

    public function compileIndexMappings(Builder $query): array
    {
        return ['index' => $query->getFrom() == '' ? '*' : $query->getFrom()];
    }
}

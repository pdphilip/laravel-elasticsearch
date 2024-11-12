<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query;

use DateTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDPhilip\Elasticsearch\Exceptions\BuilderException;
use PDPhilip\Elasticsearch\Exceptions\QueryException;
use PDPhilip\Elasticsearch\Helpers\Helpers;

class Grammar extends BaseGrammar
{
    /**
     * The index suffix.
     *
     * @var string
     */
    protected $indexSuffix = '';

    /**
     * Compile a select statement
     *
     * @param  Builder|QueryBuilder  $builder
     */
    public function compileSelect(Builder $builder): array
    {
        $query = $this->compileWheres($builder);

        $params = [
            'index' => $builder->from.$this->indexSuffix,
            'body' => [
                'query' => $query['query'],
            ],
        ];

        if ($query['filter']) {
            $params['body']['query']['bool']['filter'] = $query['filter'];
        }

        if ($query['postFilter']) {
            $params['body']['post_filter'] = $query['postFilter'];
        }

        if ($builder->aggregations) {
            $params['body']['aggregations'] = $this->compileAggregations($builder);
        }

        // Apply order, offset and limit
        if ($builder->orders) {
            $params['body']['sort'] = $this->compileOrders($builder, $builder->orders);
        }

        if ($builder->offset) {
            $params['body']['from'] = $builder->offset;
        }

        if (isset($builder->limit)) {
            $params['body']['size'] = $builder->limit;
        }

        if (! $params['body']['query']) {
            unset($params['body']['query']);
        }

        if ($builder->distinct) {
            $params['body']['collapse']['field'] = $this->getKeywordField(reset($builder->distinct), $builder);
        }

        return $params;
    }

    /**
     * Given a `$field` points to the subfield that is of type keyword.
     *
     * @return array
     */
    public function getKeywordField(string $textField, Builder $builder): string
    {
        $mapping = collect(Arr::dot($builder->getMapping()))->filter(function ($value, $key) use ($textField) {
          return $value == 'keyword' && str($key)->containsAll(explode('.', $textField));
        })->map(function ($value, $key) use ($textField, $builder) {
          return str($key)->replace(["{$builder->from}.", "mappings.", "fields.", "properties.", ".type"], '')->squish()->trim()->toString();
        })->first();

        if(!empty($mapping)){
          return $mapping;
        }

        throw new BuilderException("{$textField} does not have a keyword field.");
    }

    /**
     * Compile where clauses for a query
     */
    public function compileWheres(Builder $builder): array
    {
        $queryParts = [
            'query' => 'wheres',
            'filter' => 'filters',
            'postFilter' => 'postFilters',
        ];

        $compiled = [];

        foreach ($queryParts as $queryPart => $builderVar) {
            $clauses = $builder->$builderVar ?? [];

            $compiled[$queryPart] = $this->compileClauses($builder, $clauses);
        }

        return $compiled;
    }

    /**
     * Compile general clauses for a query
     */
    protected function compileClauses(Builder $builder, array $clauses): array
    {
        $query = [];
        $isOr = false;

        foreach ($clauses as $where) {

            if (isset($where['column']) && Str::startsWith($where['column'], $builder->from.'.')) {
                $where['column'] = Str::replaceFirst($builder->from.'.', '', $where['column']);
            }

            // We use different methods to compile different wheres
            $method = 'compileWhere'.$where['type'];
            $result = $this->{$method}($builder, $where);

            // Wrap the result with a bool to make nested wheres work
            if (count($clauses) > 0 && $where['boolean'] !== 'or') {
                $result = ['bool' => ['must' => [$result]]];
            }

            // If this is an 'or' query then add all previous parts to a 'should'
            if (! $isOr && $where['boolean'] == 'or') {
                $isOr = true;

                if ($query) {
                    $query = ['bool' => ['should' => [$query]]];
                } else {
                    $query['bool']['should'] = [];
                }
            }

            // Add the result to the should clause if this is an Or query
            if ($isOr) {
                $query['bool']['should'][] = $result;
            } else {
                // Merge the compiled where with the others
                $query = array_merge_recursive($query, $result);
            }
        }

        return $query;
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
        } elseif ($where['operator'] == 'like') {
            $query = [
                'wildcard' => [
                    $this->getKeywordField($where['column'], $builder) => str_replace('%', '*', $value),
                ],
            ];
        } elseif (in_array($where['operator'], array_keys($operatorsMap))) {
            $operator = $operatorsMap[$where['operator']];
            $query = [
                'range' => [
                    $where['column'] => [
                        $operator => $value,
                    ],
                ],
            ];
        } else {
            $query = [
                'match' => [
                    $where['column'] => [
                        // TODO: This should be an option that can be chnaged.
                        'query' => $value,
                        'operator' => 'and',
                    ],
                ],
            ];
        }

        $query = $this->applyOptionsToClause($query, $where);

        if (
            ! empty($where['not'])
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
    protected function compileWhereMatchAll(Builder $builder, array $where)
    {
        return ['match_all' => (object) []];
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
                $where['column'] => $where['value'],
            ],
        ];

        return $query;
    }

    /**
     * Compile a child clause
     */
    protected function compileWhereChild(Builder $builder, array $where): array
    {
        return $this->applyWhereRelationship($builder, $where, 'child');
    }

    /**
     * Compile an in clause
     */
    protected function compileWhereIn(Builder $builder, array $where, $not = false): array
    {
        $column = $this->getKeywordField($where['column'], $builder);
        $values = $this->getValueForWhere($builder, $where);

        $query = [
            'terms' => [
                $column => array_values($values),
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
     * Compile a not in clause
     */
    protected function compileWhereNotIn(Builder $builder, array $where): array
    {
        return $this->compileWhereIn($builder, $where, true);
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
     * Compile a not null clause
     */
    protected function compileWhereNotNull(Builder $builder, array $where): array
    {
        $where['operator'] = '!=';

        return $this->compileWhereBasic($builder, $where);
    }

    /**
     * Compile a where between clause
     *
     * @param  bool  $not
     */
    protected function compileWhereBetween(Builder $builder, array $where): array
    {
        $column = $where['column'];
        $values = $this->getValueForWhere($builder, $where);

        if ($where['not']) {
            $query = [
                'bool' => [
                    'should' => [
                        [
                            'range' => [
                                $column => [
                                    'lte' => $values[0],
                                ],
                            ],
                        ],
                        [
                            'range' => [
                                $column => [
                                    'gte' => $values[1],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        } else {
            $query = [
                'range' => [
                    $column => [
                        'gte' => $values[0],
                        'lte' => $values[1],
                    ],
                ],
            ];
        }

        return $query;
    }

    /**
     * Compile where for function score
     */
    protected function compileWhereFunctionScore(Builder $builder, array $where): array
    {
        $cleanWhere = $where;

        unset(
            $cleanWhere['function_type'],
            $cleanWhere['type'],
            $cleanWhere['boolean']
        );

        $query = [
            'function_score' => [
                $where['function_type'] => $cleanWhere,
            ],
        ];

        return $query;
    }

    /**
     * Compile a search clause
     */
    protected function compileWhereSearch(Builder $builder, array $where): array
    {
        $fields = '_all';

        if (! empty($where['options']['fields'])) {
            $fields = $where['options']['fields'];
        }

        if (is_array($fields) && ! is_numeric(array_keys($fields)[0])) {
            $fieldsWithBoosts = [];

            foreach ($fields as $field => $boost) {
                $fieldsWithBoosts[] = "{$field}^{$boost}";
            }

            $fields = $fieldsWithBoosts;
        }

        if (is_array($fields) && count($fields) > 1) {
            $type = isset($where['options']['matchType']) ? $where['options']['matchType'] : 'most_fields';

            $query = [
                'multi_match' => [
                    'query' => $where['value'],
                    'type' => $type,
                    'fields' => $fields,
                ],
            ];
        } else {
            $field = is_array($fields) ? reset($fields) : $fields;

            $query = [
                'match' => [
                    $field => [
                        'query' => $where['value'],
                    ],
                ],
            ];
        }

        if (! empty($where['options']['fuzziness'])) {
            $matchType = array_keys($query)[0];

            if ($matchType === 'multi_match') {
                $query[$matchType]['fuzziness'] = $where['options']['fuzziness'];
            } else {
                $query[$matchType][$field]['fuzziness'] = $where['options']['fuzziness'];
            }
        }

        if (! empty($where['options']['constant_score'])) {
            $query = [
                'constant_score' => [
                    'query' => $query,
                ],
            ];
        }

        return $query;
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
     * Compile a where nested doc clause
     *
     * @param  array  $where
     */
    protected function compileWhereNestedDoc(Builder $builder, $where): array
    {
        $wheres = $this->compileWheres($where['query']);

        $query = [
            'nested' => [
                'path' => $where['column'],
            ],
        ];

        $query['nested'] = array_merge($query['nested'], array_filter($wheres));

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
     * Apply the given options from a where to a query clause
     *
     * @return array
     */
    protected function applyOptionsToClause(array $clause, array $where)
    {
        if (empty($where['options'])) {
            return $clause;
        }

        $optionsToApply = ['boost', 'inner_hits'];
        $options = array_intersect_key($where['options'], array_flip($optionsToApply));

        foreach ($options as $option => $value) {
            $method = 'apply'.studly_case($option).'Option';

            if (method_exists($this, $method)) {
                $clause = $this->$method($clause, $value, $where);
            }
        }

        return $clause;
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
     * Apply inner hits options to the clause
     *
     * @param  mixed  $value
     * @param  array  $where
     */
    protected function applyInnerHitsOption(array $clause, $value, $where): array
    {
        $firstKey = key($clause);

        $clause[$firstKey]['inner_hits'] = empty($value) || $value === true ? (object) [] : (array) $value;

        return $clause;
    }

    /**
     * Compile all aggregations
     */
    public function compileAggregations(Builder $builder): array
    {
        $aggregations = [];

        foreach ($builder->aggregations as $aggregation) {
            $result = $this->compileAggregation($builder, $aggregation);

            $aggregations = array_merge($aggregations, $result);
        }

        return $aggregations;
    }

    /**
     * Compile a single aggregation
     */
    public function compileAggregation(Builder $builder, array $aggregation): array
    {
        $key = $aggregation['key'];

        $method = 'compile'.ucfirst(Str::camel($aggregation['type'])).'Aggregation';

        $compiled = [
            $key => $this->$method($aggregation),
        ];

        $compiled = [
            'index' => $builder->from.$this->indexSuffix,
            'body' => [
                'aggs' => $compiled,
            ],
        ];

        return $compiled;
    }

    /**
     * Compile filter aggregation
     */
    protected function compileFilterAggregation(array $aggregation): array
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
     * Compile nested aggregation
     */
    protected function compileNestedAggregation(array $aggregation): array
    {
        $path = is_array($aggregation['args']) ? $aggregation['args']['path'] : $aggregation['args'];

        return [
            'nested' => [
                'path' => $path,
            ],
        ];
    }

    /**
     * Compile terms aggregation
     */
    protected function compileTermsAggregation(array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        $compiled = [
            'terms' => [
                'field' => $field,
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
     * Compile date histogram aggregation
     */
    protected function compileDateHistogramAggregation(array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        $compiled = [
            'date_histogram' => [
                'field' => $field,
            ],
        ];

        if (is_array($aggregation['args'])) {
            if (isset($aggregation['args']['interval'])) {
                $compiled['date_histogram']['interval'] = $aggregation['args']['interval'];
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
     * Compile cardinality aggregation
     */
    protected function compileCardinalityAggregation(array $aggregation): array
    {
        $compiled = [
            'cardinality' => $aggregation['args'],
        ];

        return $compiled;
    }

    /**
     * Compile composite aggregation
     */
    protected function compileCompositeAggregation(array $aggregation): array
    {
        $compiled = [
            'composite' => $aggregation['args'],
        ];

        return $compiled;
    }

    /**
     * Compile date range aggregation
     */
    protected function compileDateRangeAggregation(array $aggregation): array
    {
        $compiled = [
            'date_range' => $aggregation['args'],
        ];

        return $compiled;
    }

    /**
     * Compile exists aggregation
     */
    protected function compileExistsAggregation(array $aggregation): array
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
     * Compile missing aggregation
     */
    protected function compileMissingAggregation(array $aggregation): array
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
     * Compile reverse nested aggregation
     */
    protected function compileReverseNestedAggregation(array $aggregation): array
    {
        return [
            'reverse_nested' => (object) [],
        ];
    }

    /**
     * Compile count aggregation
     */
    protected function compileCountAggregation(array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];
        $aggregation = [];
        $aggregation['type'] = 'value_count';
        $aggregation['args']['field'] = $field;
        $aggregation['args']['script'] = "doc.containsKey('{$field}') && !doc['{$field}'].empty ? 1 : 0";

        return $this->compileMetricAggregation($aggregation);
    }

    /**
     * Compile sum aggregation
     */
    public function compileSumAggregation(array $aggregation): array
    {
        return $this->compileMetricAggregation($aggregation);
    }

    /**
     * Compile avg aggregation
     */
    protected function compileAvgAggregation(array $aggregation): array
    {
        return $this->compileMetricAggregation($aggregation);
    }

    /**
     * Compile max aggregation
     */
    protected function compileMaxAggregation(array $aggregation): array
    {
        return $this->compileMetricAggregation($aggregation);
    }

    /**
     * Compile min aggregation
     */
    protected function compileMinAggregation(array $aggregation): array
    {
        return $this->compileMetricAggregation($aggregation);
    }

    /**
     * Compile metric aggregation
     */
    protected function compileMetricAggregation(array $aggregation): array
    {
        $metric = $aggregation['type'];

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
            ],
        ];
    }

    /**
     * Compile children aggregation
     */
    protected function compileChildrenAggregation(array $aggregation): array
    {
        $type = is_array($aggregation['args']) ? $aggregation['args']['type'] : $aggregation['args'];

        return [
            'children' => [
                'type' => $type,
            ],
        ];
    }

    /**
     * Compile the orders section of a query
     *
     * @param  array  $orders
     */
    protected function compileOrders(Builder $builder, $orders = []): array
    {
        $compiledOrders = [];

        foreach ($orders as $order) {
            $column = $order['column'];
            if (Str::startsWith($column, $builder->from.'.')) {
                $column = Str::replaceFirst($builder->from.'.', '', $column);
            }

            $column = $this->getKeywordField($column, $builder);

            $type = $order['type'] ?? 'basic';

            switch ($type) {
                case 'geoDistance':
                    $orderSettings = [
                        $column => $order['options']['coordinates'],
                        'order' => $order['direction'] < 0 ? 'desc' : 'asc',
                        'unit' => $order['options']['unit'] ?? 'km',
                        'distance_type' => $order['options']['distanceType'] ?? 'plane',
                    ];

                    $column = '_geo_distance';
                    break;

                default:
                    $orderSettings = [
                        'order' => $order['direction'] < 0 ? 'desc' : 'asc',
                    ];

                    $allowedOptions = ['missing', 'mode'];

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
     * Compile the given values to an Elasticsearch insert statement
     *
     * @param  Builder|QueryBuilder  $builder
     */
    public function compileInsert(Builder $builder, array $values): array
    {
        $params = [];

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        foreach ($values as $doc) {
            $doc['id'] = $doc['id'] ?? ((string) Helpers::uuid());
            if (isset($doc['child_documents'])) {
                foreach ($doc['child_documents'] as $childDoc) {
                    $params['body'][] = [
                        'index' => [
                            '_index' => $builder->from.$this->indexSuffix,
                            '_id' => $childDoc['id'],
                            'parent' => $doc['id'],
                        ],
                    ];

                    $params['body'][] = $childDoc['document'];
                }

                unset($doc['child_documents']);
            }

            $index = [
                '_index' => $builder->from.$this->indexSuffix,
                '_id' => $doc['id'],
            ];

            if (isset($doc['_routing'])) {
                $index['routing'] = $doc['_routing'];
                unset($doc['_routing']);
            } elseif ($routing = $builder->getRouting()) {
                $index['routing'] = $routing;
            }

            if ($parentId = $builder->getParentId()) {
                $index['parent'] = $parentId;
            } elseif (isset($doc['_parent'])) {
                $index['parent'] = $doc['_parent'];
                unset($doc['_parent']);
            }

            $params['body'][] = ['index' => $index];

            foreach ($doc as &$property) {
                $property = $this->getStringValue($property);
            }

            $params['body'][] = $doc;
        }

        if ($refresh = $builder->getOption('refresh')) {
            $params['refresh'] = $refresh;
        } else {
            $params['refresh'] = true;
        }

        return $params;
    }

    public function compileUpdate(Builder $builder, $values)
    {
        $clause = $this->compileSelect($builder);
        $clause['body']['conflicts'] = 'proceed';
        $scripts = [];
        $params = [];

        foreach ($values as $column => $value) {
            $value = $this->getStringValue($value);
            if (Str::startsWith($column, $builder->from.'.')) {
                $column = Str::replaceFirst($builder->from.'.', '', $column);
            }

            $param = str($column)->replace('.', '_')->toString();

            $params[$param] = $value;
            $scripts[] = 'ctx._source.'.$column.' = params.'.$param.';';
        }

        foreach ($builder->scripts as $script) {

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

        if ($refresh = $builder->getOption('refresh')) {
            $clause['refresh'] = $refresh;
        } else {
            $clause['refresh'] = true;
        }

        return $clause;
    }

    public function compileIndexMappings(Builder $builder)
    {
        return ['index' => $builder->from];
    }

    /**
     * Compile a delete query
     *
     * @param  Builder|QueryBuilder  $builder
     */
    public function compileDelete(Builder $builder): array
    {
        $clause = $this->compileSelect($builder);

        if ($refresh = $builder->getOption('refresh')) {
            $clause['refresh'] = $refresh;
        } else {
            $clause['refresh'] = true;
        }

        if ($conflict = $builder->getOption('delete_conflicts')) {
            $clause['conflicts'] = $conflict;
        }

        return $clause;
    }

    /**
     * Compile a delete query
     *
     * @param  Builder|QueryBuilder  $builder
     */
    public function compileTruncate(Builder $builder): array
    {
        $clause = $this->compileSelect($builder);

        $clause['body'] = [
            'query' => [
                'match_all' => (object) [],
            ],
        ];

        if ($refresh = $builder->getOption('refresh')) {
            $clause['refresh'] = $refresh;
        } else {
            $clause['refresh'] = true;
        }

        return $clause;
    }

    /**
     * Convert a key to an Elasticsearch-friendly format
     *
     * @param  mixed  $value
     */
    protected function convertKey($value): string
    {
        return (string) $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getDateFormat(): string
    {
        return Config::get('laravel-elasticsearch.date_format', 'Y-m-d H:i:s');
    }

    /**
     * Get the grammar's index suffix.
     */
    public function getIndexSuffix(): string
    {
        return $this->indexSuffix;
    }

    /**
     * Set the grammar's table suffix.
     *
     * @return $this
     */
    public function setIndexSuffix(string $suffix): self
    {
        $this->indexSuffix = $suffix;

        return $this;
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
     *
     * @param  Builder  $builder
     */
    protected function convertDateTime($value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return $value->format('c');
    }
}

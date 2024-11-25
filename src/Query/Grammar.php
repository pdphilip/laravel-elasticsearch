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
use PDPhilip\Elasticsearch\Helpers\Helpers;

class Grammar extends BaseGrammar
{
    /**
     * Compile a delete query
     *
     * @param  Builder|QueryBuilder  $builder
     */
    public function compileDelete(Builder $builder): array
    {
        $clause = $this->compileSelect($builder);

        $clause['refresh'] = $builder->getOption('refresh', true);
        $clause['conflicts'] = $builder->getOption('conflicts', 'abort');

        // If we don't have a query then we must be deleting everything IE truncate.
        if (! isset($clause['body']['query'])) {
            $clause['body']['query'] = $this->compileWhereMatchAll();
        }

        return $clause;
    }

    /**
     * @return array
     */
    protected function compileWhereMatchAll()
    {
        return ['match_all' => (object) []];
    }

    /**
     * {@inheritdoc}
     */
    public function compileExists(Builder $query)
    {
        return $this->compileSelect($query);
    }

  /**
   * Compile a select statement
   *
   * @param Builder $builder
   *
   * @return array
   * @throws BuilderException
   */
    public function compileSelect(Builder $builder): array
    {
        $query = $this->compileWheres($builder);

        $params = [
            'index' => $builder->from.$builder->suffix(),
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

        if (isset($builder->columns)) {
            $params['_source'] = $builder->columns;
        }

        if (isset($params['body']['query']) && ! $params['body']['query']) {
            unset($params['body']['query']);
        }

      if ($builder->aggregations) {
        $params['body']['aggs'] = $this->compileAggregations($builder);

        //If we are aggregating we set the body size to 0 to save on processing time.
        $params['body']['size'] = 0;
      }

        if ($builder->distinct) {
            $params['body']['collapse']['field'] = $this->getIndexableField(reset($builder->distinct), $builder);
        }

        return $params;
    }

    /**
     * Compile all aggregations
     */
    public function compileAggregations(Builder $builder): array
    {
        $aggregations = [];

        foreach ($builder->aggregations as $aggregation) {
            $result = $this->compileAggregation($builder, $aggregation);

            $aggregations = array_merge_recursive($aggregations, $result);
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
            $key => $this->$method($builder, $aggregation),
        ];

        if (isset($aggregation['aggregations']) && $aggregation['aggregations']->aggregations) {
          $compiled[$key]['aggs'] = $this->compileAggregations($aggregation['aggregations']);
        }

        return $compiled;
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

    public function compileIndexMappings(Builder $builder)
    {
        return ['index' => $builder->from];
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
                            '_index' => $builder->from.$builder->suffix(),
                            '_id' => $childDoc['id'],
                            'parent' => $doc['id'],
                        ],
                    ];

                    $params['body'][] = $childDoc['document'];
                }

                unset($doc['child_documents']);
            }

            $index = [
                '_index' => $builder->from.$builder->suffix(),
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

            // We don't want to save the ID as part of the doc.
            unset($doc['id']);

            $params['body'][] = ['index' => $index];

            foreach ($doc as &$property) {
                $property = $this->getStringValue($property);
            }

            $params['body'][] = $doc;
        }

        $params['refresh'] = $builder->getOption('refresh', true);

        return $params;
    }

    /**
     * Compile sum aggregation
     */
    public function compileSumAggregation(Builder $builder, array $aggregation): array
    {
        return $this->compileMetricAggregation($builder, $aggregation);
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

        $clause['refresh'] = $builder->getOption('refresh', true);

        return $clause;
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

        $clause['refresh'] = $builder->options()->get('refresh', true);

        return $clause;
    }

    /**
     * {@inheritdoc}
     */
    public function getDateFormat(): string
    {
        return Config::get('laravel-elasticsearch.date_format', 'Y-m-d H:i:s');
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
     * Compile avg aggregation
     */
    protected function compileAvgAggregation(Builder $builder,array $aggregation): array
    {
        return $this->compileMetricAggregation($builder, $aggregation);
    }

    /**
     * Compile cardinality aggregation
     */
    protected function compileCardinalityAggregation(Builder $builder,array $aggregation): array
    {
        $compiled = [
            'cardinality' => $aggregation['args'],
        ];

        return $compiled;
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
     * Compile composite aggregation
     */
    protected function compileCompositeAggregation(Builder $builder, array $aggregation): array
    {

        $compiled = [
            'composite' => [
              'sources' => $aggregation['args']
            ],
        ];

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
     * Compile metric aggregation
     */
    protected function compileMetricAggregation(Builder $builder, array $aggregation): array
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
                'size' => $builder->limit,
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
     * Compile a child clause
     */
    protected function compileWhereChild(Builder $builder, array $where): array
    {
        return $this->applyWhereRelationship($builder, $where, 'child');
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
                        ...($where['parameters'] ?? []),
                    ],
                ],
            ];
        } elseif (in_array($where['operator'], array_keys($operatorsMap))) {
            $operator = $operatorsMap[$where['operator']];
            $query = [
                'range' => [
                    $where['column'] => [
                        $operator => $value,
                        ...($where['parameters'] ?? []),

                    ],
                ],
            ];
        } else {
            $query = [
                'match' => [
                    $where['column'] => [
                        'query' => $value,
                        'operator' => 'and',
                        ...($where['parameters'] ?? []),
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
     * Given a `$field` points to the subfield that is of type keyword.
     *
     * @return array
     */
    public function getIndexableField(string $textField, Builder $builder): string
    {

        // _id doesn't need to be found.
        if ($textField == '_id') {
            return '_id';
        }

        $mapping = collect(Arr::dot($builder->getMapping()))
            ->mapWithKeys(function ($value, $key) {
                return [str($key)->after('.')->beforeLast('.')->toString() => $value];
            })
            ->filter(function ($value, $key) use ($textField) {
                return ! in_array($value, ['text', 'binary']) && str($key)->containsAll(explode('.', $textField));
            })
            ->map(function ($value, $key) {
                return str($key)->replace(['mappings.', 'fields.', 'properties.'], '')->squish()->trim()->toString();
            })->first();

        if (! empty($mapping)) {
            return $mapping;
        }

        throw new BuilderException("{$textField} does not have a keyword field.");
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
     * Compile a parent clause
     */
    protected function compileWhereParent(Builder $builder, array $where): array
    {
        return $this->applyWhereRelationship($builder, $where, 'parent');
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
        return [
            'regexp' => [
                $where['column'] => [
                    ...$where['parameters'],
                    'value' => (string) $where['value'],
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
     * Convert a key to an Elasticsearch-friendly format
     *
     * @param  mixed  $value
     */
    protected function convertKey($value): string
    {
        return (string) $value;
    }
}

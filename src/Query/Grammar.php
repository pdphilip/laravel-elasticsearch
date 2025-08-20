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
use PDPhilip\Elasticsearch\Query\DSL\DslBuilder;
use PDPhilip\Elasticsearch\Query\DSL\DslFactory;
use PDPhilip\Elasticsearch\Query\DSL\QueryCompiler;
use PDPhilip\Elasticsearch\Utils\Helpers;

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
        $dsl = new DslBuilder;
        if (! isset($values[0])) {
            $values = [$values];
        }
        // Process each document to be inserted
        foreach ($values as $doc) {

            $docId = $doc['_id'] ?? $doc['id'] ?? null;

            // Handle child documents
            if (isset($doc['child_documents'])) {
                foreach ($doc['child_documents'] as $childDoc) {
                    $childId = $childDoc['id'];

                    $childOptions = [];
                    if ($docId) {
                        $childOptions['parent'] = $docId;
                    }

                    $childIndex = DslFactory::indexOperation(
                        index: $query->getFrom(),
                        id: $childId,
                        options: $childOptions
                    );
                    $dsl->appendBody($childIndex);

                    // Add the child document content
                    $dsl->appendBody($childDoc['document']);
                }

                // Remove child_documents from the parent document
                unset($doc['child_documents']);
            }

            // Prepare main document operation options
            $options = [];

            // Handle routing
            if (isset($doc['_routing'])) {
                $options['routing'] = $doc['_routing'];
                unset($doc['_routing']);
            } elseif ($routing = $query->getRouting()) {
                $options['routing'] = $routing;
            }

            // Handle parent ID
            if ($parentId = $query->getParentId()) {
                $options['parent'] = $parentId;
            } elseif (isset($doc['_parent'])) {
                $options['parent'] = $doc['_parent'];
                unset($doc['_parent']);
            }

            // We don't want to save the ID as part of the doc
            // Unless the Model has explicitly set 'storeIdsInDocument'
            if ($query->getOption('store_ids_in_document', false)) {
                $doc['id'] = $docId;
                unset($doc['_id']);
            } else {
                unset($doc['id'], $doc['_id']);
            }
            if (! empty($doc['_op_type'])) {
                $options['op_type'] = $doc['_op_type'];
                unset($doc['_op_type']);
            } elseif ($optType = $query->getOption('op_type')) {
                $options['op_type'] = $optType;
            }

            // Add the document operation
            $index = DslFactory::indexOperation(
                index: $query->getFrom(),
                id: $docId,
                options: $options
            );
            $dsl->appendBody($index);

            // Process document properties to ensure proper formatting
            foreach ($doc as &$property) {
                $property = $this->getStringValue($property);
            }

            // Add the document content
            $dsl->appendBody($doc);
        }

        // Set refresh option
        $dsl->setRefresh($query->getOption('refresh', true));

        // Return the built DSL
        return $dsl->getDsl();
    }

    // ======================================================================
    // Read
    // ======================================================================

    /**
     * @throws BuilderException
     */
    public function compileCount($query)
    {
        $dsl = new DslBuilder;
        $dsl->setIndex($query->getFrom());
        $compiled = $this->compileWheres($query);
        $query = ! empty($compiled['query']) ? $compiled['query'] : DslFactory::matchAll();
        $dsl->setBody(['query'], $query);

        return $dsl->getDsl();
    }

    /**
     * Compile a select statement
     *
     * @param  Builder  $query
     *
     * @throws BuilderException
     */
    public function compileSelect($query): array
    {
        // Remove parentField on top level query if present
        $query->options()->remove('parentField');

        $dsl = new DslBuilder;
        $dsl->setIndex($query->getFrom());
        $compiled = $this->compileWheres($query);
        $dsl->setBody(['query'], $compiled['query']);
        if ($compiled['filter']) {
            if (! $dsl->getBodyValue(['query', 'bool'])) {
                $currentQuery = $dsl->getBodyValue(['query']);
                $dsl->unsetBody(['query']);
                $dsl->setBody(['query', 'bool', 'must'], $currentQuery);
            }
            $dsl->setBody(['query', 'bool', 'filter'], $compiled['filter']);
        }

        if ($compiled['postFilter']) {
            $dsl->setBody(['post_filter'], $compiled['postFilter']);
        }
        $compiledOrders = [];
        if ($query->orders) {
            $compiledOrders = $this->compileOrders($query, $query->orders);
        }
        if ($query->sorts) {
            $compiledOrders = $this->compileSorts($query, $query->sorts, $compiledOrders);
        }
        if ($compiledOrders) {
            $dsl->setBody(['sort'], $compiledOrders);
        }
        if ($query->highlight) {
            $dsl->setBody(['highlight'], $this->compileHighlight($query, $query->highlight));
        }

        if ($query->offset) {
            $dsl->setBody(['from'], $query->offset);
        }
        $dsl->setBody(['size'], $query->getLimit());

        if (isset($query->columns)) {
            $dsl->setSource($query->columns);
        }
        if ($query->bodyParameters) {
            foreach ($query->bodyParameters as $name => $parameter) {
                $dsl->setBody([$name], $parameter);
            }
        }

        // Distinct and Aggregations
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
                $dsl->setBody(['aggs'], $this->compileNestedTermAggregations($fields, $query, $aggs));
                $dsl->setBody(['size'], $query->getSetLimit() ?? 0);
                $dsl->unsetBody(['sort']);
            } else {
                // else nothing to aggregate - just a normal query as all records will be distinct anyway
                $query->distinct = false;
            }
        }
        // Else if we have bucket aggregations
        elseif ($query->bucketAggregations) {
            $sorts = $dsl->getBodyValue(['sort']);
            if ($afterCount = $dsl->getBodyValue(['from'])) {
                $dsl->unsetBody(['from']);
                // This of course could be a problem if the user is using from for pagination and they go deep
                // Leaving it for now
                $query->after = $query->getGroupByAfterKey($afterCount);
            }

            $dsl->setBody(['aggs'], $this->compileBucketAggregations($query, $sorts));
            $dsl->setBody(['size'], 0);

            //            return $dsl->getDsl();
        }

        // Else if we have metrics aggregations
        elseif ($query->metricsAggregations) {
            $dsl->setBody(['aggs'], $this->compileMetricAggregations($query));
            $dsl->setBody(['size'], 0);
        }

        if (! $dsl->getBodyValue(['query'])) {
            $dsl->unsetBody(['query']);
        }
        if ($query->pitId) {
            $dsl->setBody(['pit', 'id'], $query->pitId);
            $dsl->unsetOption(['index']);
            $dsl->setBody(['pit', 'keep_alive'], $query->keepAlive);
            $dsl->appendOption(['body', 'sort'], DslFactory::sortByShardDoc());
        }

        if ($query->searchAfter) {
            $dsl->setBody(['search_after'], $query->searchAfter);
        }

        return $dsl->getDsl();
    }

    /**
     * Compile where clauses for a query
     *
     * @param  Builder  $query
     */
    public function compileWheres($query): array
    {
        $compiled['query'] = $this->compileQuery($query, $query->wheres);
        $compiled['filter'] = $this->compileQuery($query, $query->filters ?? []);
        $compiled['postFilter'] = $this->compileQuery($query, $query->postFilters ?? []);

        return $compiled;
    }

    protected function compileQuery(Builder $builder, array $wheres = []): array
    {
        if (! $wheres) {
            return [];
        }
        $dslCompiler = new QueryCompiler;
        foreach ($wheres as $where) {
            $isOr = str_starts_with($where['boolean'], 'or');
            $isNot = str_ends_with($where['boolean'], 'not');

            if (! empty($where['not'])) {
                $isNot = true;
            }
            // Adjust operator to lowercase
            if (isset($where['operator'])) {
                $where['operator'] = strtolower($where['operator']);
                [$operator, $isNegation] = $this->parseNegationOperator($where['operator'], $this->getValueForWhere($builder, $where));
                $where['operator'] = $operator;
                if ($isNegation) {
                    $isNot = ! $isNot;
                }
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

                $where['column'] = $this->prependParentField($where['column'], $builder);
            }
            $method = 'compileWhere'.$where['type'];
            $result = $this->{$method}($builder, $where);
            $dslCompiler->setResult($result, $isOr, $isNot);

        }

        return $dslCompiler->compileQuery();
    }

    // ----------------------------------------------------------------------
    // Where Compilers
    // ----------------------------------------------------------------------

    public function compileWhereMatchAll(): array
    {
        return DslFactory::matchAll();
    }

    /**
     * Compile a general where clause
     *
     * @throws BuilderException
     */
    protected function compileWhereBasic(Builder $builder, array $where): array
    {
        $value = $this->getValueForWhere($builder, $where);
        $field = $where['column'];
        $options = $where['options'] ?? [];

        $operatorsMap = [
            '>' => 'gt',
            '>=' => 'gte',
            '<' => 'lt',
            '<=' => 'lte',
        ];

        if (is_null($value) || $where['operator'] == 'exists') {
            $query = DslFactory::exists($field, $options);

        } elseif (in_array($where['operator'], ['like', 'not like'])) {
            $field = $this->getIndexableField($field, $builder);
            $wildcardValue = str_replace('%', '*', $value);
            // Add wildcards to if not present
            if (! Str::contains($wildcardValue, '*')) {
                $wildcardValue = '*'.$wildcardValue.'*';
            }
            $query = DslFactory::wildcard($field, $wildcardValue, $options);

        } elseif (in_array($where['operator'], array_keys($operatorsMap))) {
            $operator = $operatorsMap[$where['operator']];
            $field = $this->getIndexableField($field, $builder);
            $query = DslFactory::range($field, [$operator => $value], $options);

        } else {
            $field = $this->getIndexableField($field, $builder);
            $query = DslFactory::term($field, $value, $options);
        }

        return $this->applyOptionsToClause($query, $where);

    }

    protected function compileWhereMatch(Builder $builder, array $where): array
    {
        $field = $where['column'];
        $values = $this->getValueForWhere($builder, $where);
        $options = $where['options'] ?? [];
        $options['operator'] = $options['operator'] ?? 'and';
        $query = DslFactory::match($field, $values, $options);

        return $this->applyOptionsToClause($query, $where);
    }

    /**
     * Compile an in clause
     *
     * @throws BuilderException
     */
    protected function compileWhereIn(Builder $builder, array $where, $not = false): array
    {
        $field = $this->getIndexableField($where['column'], $builder);
        $values = $this->getValueForWhere($builder, $where);
        $options = $where['options'] ?? [];
        $query = DslFactory::terms($field, $values, $options);

        return $this->applyOptionsToClause($query, $where);
    }

    /**
     * Compile a null clause
     *
     * @throws BuilderException
     */
    protected function compileWhereExists(Builder $builder, array $where): array
    {
        $where['operator'] = '=';

        return $this->compileWhereBasic($builder, $where);
    }

    /**
     * Compile a date clause
     *
     * @throws BuilderException
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
        $options = $where['options'] ?? [];

        $conditions = [
            'gte' => $values[0],
            'lte' => $values[1],
        ];

        return DslFactory::range($column, $conditions, $options);
    }

    /**
     * Compile where for function score
     */
    protected function compileWhereFunctionScore(Builder $builder, array $where): array
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
        $functionType = $where['functionType'];
        $functionOptions = $where['options'];

        return DslFactory::functionScore($compiled['query'], $functionType, $functionOptions);
    }

    /**
     * Compile a geo distance clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     */
    protected function compileWhereGeoDistance($builder, $where): array
    {
        $options = $where['options'] ?? [];

        return DslFactory::geoDistance($where['column'], $where['distance'], $where['location'], $options);
    }

    /**
     * Compile a where geo bounds clause
     */
    protected function compileWhereGeoBox(Builder $builder, array $where): array
    {
        $options = $where['options'] ?? [];

        return DslFactory::geoBoundingBox($where['column'], $where['bounds'], $options);
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
        // Compile the inner query
        $wheres = $this->compileWheres($where['query']);
        $path = $where['column'];
        if (! $wheres['query']) {
            $wheres['query'] = DslFactory::exists($path);
        }

        $options = $where['options'] ?? [];
        $wheres = array_filter($wheres);

        $query = DslFactory::nested($path, $wheres, $options);

        return $this->applyOptionsToClause($query, $where);
    }

    public function _buildInnerHits($innerQuery)
    {

        $innerHits = [];
        $compiledOrders = [];
        if ($innerQuery->orders) {
            $compiledOrders = $this->compileOrders($innerQuery, $innerQuery->orders);
        }
        if ($innerQuery->sorts) {
            $compiledOrders = $this->compileSorts($innerQuery, $innerQuery->sorts, $compiledOrders);
        }
        if ($compiledOrders) {
            $innerHits['sort'] = $compiledOrders;
        }

        if ($innerQuery->offset) {
            $innerHits['from'] = $innerQuery->offset;
        }
        if ($size = $innerQuery->getSetLimit()) {
            $innerHits['size'] = $size;
        }

        return $innerHits;
    }

    /**
     * Compile a where nested clause
     *
     * @param  array  $where
     */
    protected function compileWhereInnerNested(Builder $builder, $where): array
    {
        // Compile the inner filter
        $innerQuery = $where['query'];
        $query = $this->compileWheres($innerQuery);
        $innerHits = $this->_buildInnerHits($innerQuery);

        return DslFactory::innerNested($where['column'], $query['query'], $innerHits);

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
        $type = $where['relationType'];
        $id = $where['id'];
        $options = $where['options'] ?? [];

        return DslFactory::parentId($type, $id, $options);
    }

    /**
     * @throws BuilderException
     */
    protected function compileWherePrefix(Builder $builder, array $where): array
    {
        $field = $this->getIndexableField($where['column'], $builder);
        $value = $where['value'];
        $options = $where['options'] ?? [];

        return DslFactory::prefix($field, $value, $options);
    }

    protected function compileWhereRaw(Builder $builder, array $where): array
    {
        return $where['sql']; // Return the raw query as-is
    }

    /**
     * Compile a date clause
     *
     * @throws BuilderException
     */
    protected function compileWhereRegex(Builder $builder, array $where): array
    {

        // Define the allowed options for regex queries
        // TODO: Catch this upfront in the builder
        $allowedArgs = [
            'flags',
            'case_insensitive',
            'max_determinized_states',
            'rewrite',
        ];
        $options = $this->getAllowedOptions($where['options'] ?? [], $allowedArgs);
        $field = $this->getIndexableField($where['column'], $builder);
        $value = Helpers::escape($where['value']);

        return DslFactory::regexp($field, $value, $options);
    }

    /**
     * Compile a match_phrase clause
     */
    protected function compileWherePhrase(Builder $builder, array $where): array
    {
        $field = $where['column'];
        $value = $where['value'];
        $options = $where['options'] ?? [];

        return DslFactory::matchPhrase($field, $value, $options);
    }

    /**
     * Compile a match_phrase clause
     */
    protected function compileWherePhrasePrefix(Builder $builder, array $where): array
    {

        $field = $where['column'];
        $value = $where['value'];
        $options = $where['options'] ?? [];

        return DslFactory::matchPhrasePrefix($field, $value, $options);
    }

    /**
     * Compile a term fuzzy clause
     */
    protected function compileWhereFuzzy(Builder $builder, array $where): array
    {

        $field = $where['column'];
        $value = $where['value'];
        $options = $where['options'] ?? [];

        return DslFactory::fuzzy($field, $value, $options);
    }

    /**
     * Compile a term clause
     *
     * @throws BuilderException
     */
    protected function compileWhereTerm(Builder $builder, array $where): array
    {

        $field = $this->getIndexableField($where['column'], $builder);
        $value = $where['value'];
        $options = $where['options'] ?? [];

        return DslFactory::term($field, $value, $options);
    }

    /**
     * Compile a script clause
     */
    protected function compileWhereScript(Builder $builder, array $where): array
    {
        $source = $where['script'];
        $options = $where['options'] ?? [];

        return DslFactory::script($source, $options);
    }

    /**
     * Compile a search clause for Elasticsearch.
     */
    protected function compileWhereSearch(Builder $builder, array $where): array
    {
        $value = $where['value'];
        $options = $where['options'] ?? [];

        $constantScore = false;
        if (isset($options['constant_score'])) {
            $constantScore = $options['constant_score'];
            unset($options['constant_score']);
        }
        $query = DslFactory::multiMatch($value, $options);

        if ($constantScore) {
            return DslFactory::constantScore($query);
        }

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

        return $this->applyOptionsToClause($query, $where);
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
            $column = $this->prependParentField($column, $builder);
            $column = $this->getIndexableField($column, $builder);

            $type = $order['type'] ?? 'basic';

            switch ($type) {
                case 'geoDistance':
                    $orderSettings = [
                        $column => $order['options']['coordinates'],
                        'order' => $order['direction'] < 0 ? 'desc' : 'asc',
                    ];
                    if (! empty($order['options']['unit'])) {
                        $orderSettings['unit'] = $order['options']['unit'];
                    }
                    if (! empty($order['options']['mode'])) {
                        $orderSettings['mode'] = $order['options']['mode'];
                    }
                    if (! empty($order['options']['distance_type'])) {
                        $orderSettings['distance_type'] = $order['options']['distance_type'];
                    }
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
     * @throws BuilderException
     */
    protected function compileSorts(Builder $builder, $sorts, $compiledOrders): array
    {
        foreach ($sorts as $column => $sort) {
            $found = false;
            $column = $this->prependParentField($column, $builder);
            $column = $this->getIndexableField($column, $builder);
            if ($compiledOrders) {
                foreach ($compiledOrders as $i => $compiledOrder) {
                    if (array_key_exists($column, $compiledOrder)) {
                        $compiledOrders[$i][$column] = array_merge($compiledOrder[$column], $sort);
                        $found = true;
                        break;
                    }
                }
            }
            if (! $found) {
                $compiledOrders[] = [$column => $sort];
            }
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
            } else {
                $compiledHighlights['fields'][] = ['*' => (object) []];
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
        // Get the current field to process
        $currentField = array_shift($fields);
        $field = $this->getIndexableField($currentField, $query);

        $metricAggs = [];
        if (! empty($aggs[$currentField])) {
            $key = $aggs[$currentField]['key'];
            $metricAgg = $aggs[$currentField];
            unset($metricAgg['key']);
            $metricAggs[$key] = $metricAgg;
        }

        // Prepare sorting
        $sorts = $query->sorts;
        $orders = collect($query->orders);
        $termsOrders = [];

        // Add count-based sorting if specified
        if (isset($sorts['_count'])) {
            $termsOrders[] = $sorts['_count'] == 1 ? ['_count' => 'asc'] : ['_count' => 'desc'];
        }

        // Add field-based sorting if specified
        $fieldOrder = $orders->where('column', $currentField)->first();
        if ($fieldOrder) {
            $termsOrders[] = $fieldOrder['direction'] == 1 ? ['_key' => 'asc'] : ['_key' => 'desc'];
        }

        // Process nested fields recursively
        $subAggs = [];
        if (! empty($fields)) {
            $subAggs = $this->compileNestedTermAggregations($fields, $query, $aggs);
        }

        return DslFactory::nestedTermsAggregation(
            fieldName: $currentField,
            field: $field,
            size: $query->getLimit(),
            orders: $termsOrders,
            metricAggs: $metricAggs,
            subAggs: $subAggs
        );
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
        // Process each bucket aggregation
        foreach ($builder->bucketAggregations as $aggregation) {
            // Apply metric aggregations inside the bucket if they exist
            if (! empty($metricsAggregations)) {
                $aggregation['aggregations'] = $builder->newQuery();
                // @phpstan-ignore-next-line
                $aggregation['aggregations']->metricsAggregations = $builder->metricsAggregations;
            }

            $result = $this->compileAggregation($builder, $aggregation);
            $aggregations = $aggregations->mergeRecursive($result);
        }

        if ($sorts) {
            $aggregations = collect(DslFactory::applySortsToAggregations($aggregations->all(), $sorts));
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
        return DslFactory::applyBoost($clause, $value);
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

        $args = $aggregation['args'];
        $options = $aggregation['options'] ?? [];

        return DslFactory::categorizeText($args, $options);
    }

    /**
     * Compile composite aggregation
     */
    protected function compileCompositeAggregation(Builder $builder, array $aggregation): array
    {
        $sources = $aggregation['args'];
        $size = $builder->getSetLimit() ?? 0;
        $afterKey = $builder->after ?? null;
        $options = $aggregation['options'] ?? [];

        return DslFactory::composite($sources, $size, $afterKey, $options);
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

        $options = $aggregation['options'] ?? [];
        $fields = $aggregation['args'];

        return DslFactory::matrixStats($fields, $options);
    }

    /**
     * Compile metric aggregation
     */
    protected function compileMetricAggregation(Builder $builder, array $aggregation): array
    {
        $metric = $aggregation['type'];
        $options = $aggregation['options'] ?? [];

        if (is_array($aggregation['args']) && isset($aggregation['args']['script'])) {
            return DslFactory::scriptMetricAggregation(
                metric: $metric,
                script: $aggregation['args']['script'],
                options: $options
            );
        }

        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        return DslFactory::metricAggregation($metric, $field, $options);
    }

    /**
     * Compile date histogram aggregation
     */
    protected function compileDateHistogramAggregation(Builder $builder, array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];
        $options = $aggregation['options'] ?? [];

        $fixedInterval = null;
        $calendarInterval = null;
        $minDocCount = null;
        $extendedBounds = null;

        if (is_array($aggregation['args'])) {
            // Extract interval parameters
            if (isset($aggregation['args']['fixed_interval'])) {
                $fixedInterval = $aggregation['args']['fixed_interval'];
            }

            if (isset($aggregation['args']['calendar_interval'])) {
                $calendarInterval = $aggregation['args']['calendar_interval'];
            }

            // Extract min doc count
            if (isset($aggregation['args']['min_doc_count'])) {
                $minDocCount = $aggregation['args']['min_doc_count'];
            }

            // Extract extended bounds
            if (isset($aggregation['args']['extended_bounds']) && is_array($aggregation['args']['extended_bounds'])) {
                $extendedBounds = [
                    'min' => $this->convertDateTime($aggregation['args']['extended_bounds'][0]),
                    'max' => $this->convertDateTime($aggregation['args']['extended_bounds'][1]),
                ];
            }
        }

        return DslFactory::dateHistogram(
            field: $field,
            fixedInterval: $fixedInterval,
            calendarInterval: $calendarInterval,
            minDocCount: $minDocCount,
            extendedBounds: $extendedBounds,
            options: $options
        );
    }

    /**
     * Compile date range aggregation
     */
    protected function compileDateRangeAggregation(Builder $builder, array $aggregation): array
    {
        $args = $aggregation['args'];
        $options = $aggregation['options'] ?? [];

        return DslFactory::dateRange($args, $options);
    }

    /**
     * Compile exists aggregation
     */
    protected function compileExistsAggregation(Builder $builder, array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];
        $options = $aggregation['options'] ?? [];

        return DslFactory::exists($field, $options);

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

        // Create the filter aggregation
        return DslFactory::filterAggregation($allFilters);
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
        $options = $aggregation['options'] ?? [];

        return DslFactory::missingAggregation($field, $options);
    }

    /**
     * Compile nested aggregation
     */
    protected function compileNestedAggregation(Builder $builder, array $aggregation): array
    {
        $path = is_array($aggregation['args']) ? $aggregation['args']['path'] : $aggregation['args'];
        $options = $aggregation['options'] ?? [];

        return DslFactory::nestedAggregation($path, $options);
    }

    /**
     * Compile reverse nested aggregation
     */
    protected function compileReverseNestedAggregation(Builder $builder, array $aggregation): array
    {
        $options = $aggregation['options'] ?? [];

        return DslFactory::reverseNestedAggregation($options);
    }

    /**
     * Compile terms aggregation
     *
     * @throws BuilderException
     */
    protected function compileTermsAggregation(Builder $builder, array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['key'];
        $indexableField = $this->getIndexableField($field, $builder);

        // Set the base options
        $options = [
            'size' => $builder->getLimit(),
        ];

        if (is_array($aggregation['args'])) {
            $additionalOptions = DslFactory::filterTermsAggregationOptions($aggregation['args']);
            $options = array_merge($options, $additionalOptions);
        }

        // Create the terms aggregation
        return DslFactory::termsAggregation($indexableField, $builder->getLimit(), $options);
    }

    /**
     * Apply inner hits options to the clause
     */
    protected function applyInnerHitsOption(array $clause, $options, $where): array
    {
        $innerHits = $this->_buildInnerHits($where['query']);

        return DslFactory::applyInnerHits($clause, $innerHits);
    }

    /**
     * Get value for the where
     */
    protected function getValueForWhere(?Builder $builder, array $where): mixed
    {
        $value = match ($where['type']) {
            'In', 'Between' => $where['values'],
            'Exists' => null,
            default => $where['value'],
        };

        return $this->getStringValue($value);
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

    // ----------------------------------------------------------------------
    // PIT Api
    // ----------------------------------------------------------------------
    public function compileOpenPit(Builder $query)
    {
        $dsl = new DslBuilder;
        $dsl->setIndex($query->getFrom());
        $dsl->setOption(['keep_alive'], $query->keepAlive);

        return $dsl->getDsl();
    }

    public function compileClosePit(Builder $query)
    {
        $dsl = new DslBuilder;
        $dsl->setIndex($query->getFrom());
        $dsl->setBody(['id'], $query->pitId);

        return $dsl->getDsl();
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

    public function prependParentField($field, Builder $builder): string
    {
        if (! empty($parentField = $builder->options()->get('parentField'))) {
            if (Str::startsWith($field, $parentField)) {
                return $field;
            }

            return $parentField.'.'.$field;
        }

        return $field;
    }

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
        if (in_array($textField, ['_count', '_score'])) {
            return $textField;
        }

        // Checks if there is a mapping_map set for this field and return is ahead of a mapping check.
        if (! empty($queryFieldMap = $builder->options()->get('mapping_map')) && ! empty($queryFieldMap[$textField])) {
            return $queryFieldMap[$textField];
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

    protected function parseNegationOperator($operator, $value): array
    {

        if ($operator == 'not like' && ! is_null($value)) {
            $operator = 'like';

            return [$operator, true];
        }
        if ($operator == '<>' && ! is_null($value)) {
            return [$operator, true];
        }
        if ($operator == '!=' && ! is_null($value)) {
            $operator = '=';

            return [$operator, true];
        }
        if ($operator == '=' && is_null($value)) {
            return [$operator, true];
        }
        if ($operator == 'exists' && ! $value) {
            return [$operator, true];
        }

        return [$operator, false];
    }
}

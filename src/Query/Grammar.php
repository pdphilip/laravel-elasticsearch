<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query;

use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Exceptions\BuilderException;
use PDPhilip\Elasticsearch\Query\DSL\DslBuilder;
use PDPhilip\Elasticsearch\Query\DSL\DslFactory;
use PDPhilip\Elasticsearch\Query\Grammar\Concerns\CompilesAggregations;
use PDPhilip\Elasticsearch\Query\Grammar\Concerns\CompilesOrders;
use PDPhilip\Elasticsearch\Query\Grammar\Concerns\CompilesWheres;
use PDPhilip\Elasticsearch\Query\Grammar\Concerns\FieldUtilities;

/**
 * ES Query Grammar
 *
 * The heavy lifting is in the concerns:
 * - CompilesWheres: where clauses
 * - CompilesAggregations: groupBy/metrics
 * - CompilesOrders: orderBy
 * - FieldUtilities: field mapping, value conversion
 */
class Grammar extends BaseGrammar
{
    use CompilesAggregations;
    use CompilesOrders;
    use CompilesWheres;
    use FieldUtilities;

    // ======================================================================
    // CRUD Entry Points
    // ======================================================================

    /**
     * @param  Builder  $query
     */
    public function compileInsert($query, array $values): array
    {
        $dsl = new DslBuilder;
        if (! isset($values[0])) {
            $values = [$values];
        }

        foreach ($values as $doc) {
            $docId = $doc['_id'] ?? $doc['id'] ?? null;

            // Handle child documents first
            if (isset($doc['child_documents'])) {
                foreach ($doc['child_documents'] as $childDoc) {
                    $childId = $childDoc['id'];
                    $childOptions = $docId ? ['parent' => $docId] : [];

                    $dsl->appendBody(DslFactory::indexOperation(
                        index: $query->getFrom(),
                        id: $childId,
                        options: $childOptions
                    ));
                    $dsl->appendBody($childDoc['document']);
                }
                unset($doc['child_documents']);
            }

            // Build options for main doc
            $options = [];

            if (isset($doc['_routing'])) {
                $options['routing'] = $doc['_routing'];
                unset($doc['_routing']);
            } elseif ($routing = $query->getRouting()) {
                $options['routing'] = $routing;
            }

            if ($parentId = $query->getParentId()) {
                $options['parent'] = $parentId;
            } elseif (isset($doc['_parent'])) {
                $options['parent'] = $doc['_parent'];
                unset($doc['_parent']);
            }

            // ID handling - don't store in doc unless explicitly configured
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

            $dsl->appendBody(DslFactory::indexOperation(
                index: $query->getFrom(),
                id: $docId,
                options: $options
            ));

            // Convert DateTime values
            foreach ($doc as &$property) {
                $property = $this->getStringValue($property);
            }

            $dsl->appendBody($doc);
        }

        $dsl->setRefresh($query->getOption('refresh', true));

        return $dsl->getDsl();
    }

    /**
     * @param  Builder  $query
     *
     * @throws BuilderException
     */
    public function compileSelect($query): array
    {
        $query->options()->remove('parentField');

        $dsl = new DslBuilder;
        $dsl->setIndex($query->getFrom());

        // Where clauses
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

        // Ordering
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

        // Highlighting
        if ($query->highlight) {
            $dsl->setBody(['highlight'], $this->compileHighlight($query, $query->highlight));
        }

        // Pagination
        if ($query->offset) {
            $dsl->setBody(['from'], $query->offset);
        }
        $dsl->setBody(['size'], $query->getLimit());

        // Field selection
        if (isset($query->columns)) {
            $dsl->setSource($query->columns);
        }

        // Custom body params
        if ($query->bodyParameters) {
            foreach ($query->bodyParameters as $name => $parameter) {
                $dsl->setBody([$name], $parameter);
            }
        }

        // Aggregations - distinct, groupBy, or metrics
        $this->compileSelectAggregations($query, $dsl);

        // Cleanup empty query
        if (! $dsl->getBodyValue(['query'])) {
            $dsl->unsetBody(['query']);
        }

        // PIT (Point In Time) support
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
     * @throws BuilderException
     */
    private function compileSelectAggregations(Builder $query, DslBuilder $dsl): void
    {
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
                $query->distinct = false;
            }
        } elseif ($query->bulkDistinct) {
            if ($query->columns && $query->columns !== ['*']) {
                $fields = Arr::wrap($query->columns);
                $aggs = [];

                foreach ($fields as $field) {
                    $aggs = [...$aggs, ...$this->compileNestedTermAggregations([$field], $query)];
                }

                $dsl->setBody(['aggs'], $aggs);
                $dsl->setBody(['size'], $query->getSetLimit() ?? 0);
                $dsl->unsetBody(['sort']);
            } else {
                $query->bulkDistinct = false;
            }
        } elseif ($query->bucketAggregations) {
            $sorts = $dsl->getBodyValue(['sort']);
            if ($afterCount = $dsl->getBodyValue(['from'])) {
                $dsl->unsetBody(['from']);
                $query->after = $query->getGroupByAfterKey($afterCount);
            }

            $dsl->setBody(['aggs'], $this->compileBucketAggregations($query, $sorts));
            $dsl->setBody(['size'], $query->getSetLimit() ?? 0);
        } elseif ($query->metricsAggregations) {
            $dsl->setBody(['aggs'], $this->compileMetricAggregations($query));
            $dsl->setBody(['size'], $query->getSetLimit() ?? 0);
        }
    }

    public function compileCount(Builder $query): array
    {
        $dsl = new DslBuilder;
        $dsl->setIndex($query->getFrom());
        $compiled = $this->compileWheres($query);
        $query = ! empty($compiled['query']) ? $compiled['query'] : DslFactory::matchAll();
        $dsl->setBody(['query'], $query);

        return $dsl->getDsl();
    }

    /**
     * Compile UPDATE
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
            $params = [...$params, ...$script['options']['params']];
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

    /**
     * @param  Builder  $query
     *
     * @throws BuilderException
     */
    public function compileDelete($query): array
    {
        $clause = $this->compileSelect($query);
        $clause['refresh'] = $query->options()->get('refresh', true);
        $clause['conflicts'] = $query->options()->get('conflicts', 'abort');

        if (! isset($clause['body']['query'])) {
            $clause['body']['query'] = ['match_all' => (object) []];
        }

        return $clause;
    }

    /**
     * @param  Builder  $query
     *
     * @throws BuilderException
     */
    public function compileTruncate($query): array
    {
        $clause = $this->compileSelect($query);
        $clause['body'] = [
            'query' => ['match_all' => (object) []],
        ];
        $clause['refresh'] = $query->getOption('refresh', true);

        return $clause;
    }

    // ======================================================================
    // PIT (Point In Time) API
    // ======================================================================

    public function compileOpenPit(Builder $query): array
    {
        $dsl = new DslBuilder;
        $dsl->setIndex($query->getFrom());
        $dsl->setOption(['keep_alive'], $query->keepAlive);

        return $dsl->getDsl();
    }

    public function compileClosePit(Builder $query): array
    {
        $dsl = new DslBuilder;
        $dsl->setIndex($query->getFrom());
        $dsl->setBody(['id'], $query->pitId);

        return $dsl->getDsl();
    }

    // ======================================================================
    // Index Mapping
    // ======================================================================

    public function compileIndexMappings(Builder $query): array
    {
        return ['index' => $query->getFrom() == '' ? '*' : $query->getFrom()];
    }
}

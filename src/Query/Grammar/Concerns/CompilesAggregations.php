<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query\Grammar\Concerns;

use PDPhilip\Elasticsearch\Exceptions\BuilderException;
use PDPhilip\Elasticsearch\Query\Builder;
use PDPhilip\Elasticsearch\Query\DSL\DslFactory;

/**
 * Aggregation compilation - buckets, metrics, the works.
 * ES aggregations are powerful but verbose. This tames the DSL beast.
 */
trait CompilesAggregations
{
    // Simple metrics just need field + type, no special handling
    protected const SIMPLE_METRIC_AGGREGATIONS = [
        'sum', 'avg', 'min', 'max', 'value_count', 'cardinality',
        'stats', 'extended_stats', 'percentiles', 'string_stats',
        'median_absolute_deviation', 'boxplot',
    ];

    /**
     * Compile nested terms aggregations for distinct queries.
     * Recursively nests terms aggs when selecting multiple fields.
     *
     * @throws BuilderException
     */
    protected function compileNestedTermAggregations($fields, Builder $query, $aggs = []): array
    {
        $currentField = array_shift($fields);
        $field = $this->getIndexableField($currentField, $query);

        $metricAggs = [];
        if (! empty($aggs[$currentField])) {
            $key = $aggs[$currentField]['key'];
            $metricAgg = $aggs[$currentField];
            unset($metricAgg['key']);
            $metricAggs[$key] = $metricAgg;
        }

        // Figure out sorting
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

        // Recurse for remaining fields
        $subAggs = [];
        if (! empty($fields)) {
            $subAggs = $this->compileNestedTermAggregations($fields, $query, $aggs);
        }

        return DslFactory::nestedTermsAggregation(
            fieldName: $currentField,
            field: $field,
            size: $query->getSetLimit() ?? 10,
            orders: $termsOrders,
            metricAggs: $metricAggs,
            subAggs: $subAggs
        );
    }

    /**
     * Compile bucket aggregations (groupBy, ranges, etc.)
     */
    protected function compileBucketAggregations(Builder $builder, $sorts = null): array
    {
        $aggregations = collect();
        $metricsAggregations = [];

        if ($builder->metricsAggregations) {
            $metricsAggregations = $this->compileMetricAggregations($builder);
        }

        foreach ($builder->bucketAggregations as $aggregation) {
            // Nest metrics inside bucket if both exist
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
     * Compile metric aggregations (sum, avg, stats, etc.)
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
     * Route a single aggregation to its compiler.
     * Simple metrics go straight to DslFactory, others get special handling.
     */
    protected function compileAggregation(Builder $builder, array $aggregation): array
    {
        $key = $aggregation['key'];
        $type = $aggregation['type'];
        $args = $aggregation['args'];
        $options = $aggregation['options'] ?? [];

        // Most metrics are simple - just field + type
        if (in_array($type, self::SIMPLE_METRIC_AGGREGATIONS)) {
            $compiledPayload = $this->compileMetricAggregation($builder, $aggregation);
        } else {
            // Everything else has some quirk that needs handling
            $compiledPayload = match ($type) {
                // Metrics with custom logic
                'count' => $this->compileCountAggregation($args),
                'matrix_stats' => DslFactory::matrixStats($args, $options),

                // Bucket aggregations - some simple, some not
                'terms' => $this->compileTermsAggregation($builder, $aggregation),
                'range' => DslFactory::rangeAggregation($args['field'], $args['ranges']),
                'date_histogram' => $this->compileDateHistogramAggregation($aggregation),
                'date_range' => DslFactory::dateRange($args['field'], $args['ranges'], $args['options'] ?? []),
                'filter' => $this->compileFilterAggregation($args),
                'missing' => DslFactory::missingAggregation(is_array($args) ? $args['field'] : $args, $options),
                'exists' => DslFactory::exists(is_array($args) ? $args['field'] : $args, $options),
                'nested' => DslFactory::nestedAggregation(is_array($args) ? $args['path'] : $args, $options),
                'reverse_nested' => DslFactory::reverseNestedAggregation($options),
                'children' => ['children' => ['type' => is_array($args) ? $args['type'] : $args]],
                'composite' => DslFactory::composite($args, $builder->getSetLimit() ?? 0, $builder->after ?? null, $options),
                'categorize_text' => DslFactory::categorizeText($args, $options),

                // Unknown type? Try dynamic dispatch as fallback
                default => $this->{$this->getAggregationMethod($type)}($builder, $aggregation),
            };
        }

        $compiled = [$key => $compiledPayload];

        // Nest sub-aggregations if present
        if (isset($aggregation['aggregations']) && $aggregation['aggregations']->metricsAggregations) {
            $compiled[$key]['aggs'] = $this->compileMetricAggregations($aggregation['aggregations']);
        }

        return $compiled;
    }

    private function getAggregationMethod(string $type): string
    {
        return 'compile'.ucfirst(\Illuminate\Support\Str::camel($type)).'Aggregation';
    }

    // ----------------------------------------------------------------------
    // Individual aggregation compilers
    // Only the ones that need special logic. Simple ones go through DslFactory directly.
    // ----------------------------------------------------------------------

    /**
     * Base metric compiler - handles all SIMPLE_METRIC_AGGREGATIONS
     */
    protected function compileMetricAggregation(Builder $builder, array $aggregation): array
    {
        $metric = $aggregation['type'];
        $options = $aggregation['options'] ?? [];

        // Script-based metric
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
     * Count needs a script to handle missing/empty fields properly
     */
    protected function compileCountAggregation(mixed $args): array
    {
        $field = is_array($args) ? $args['field'] : $args;

        return DslFactory::scriptMetricAggregation(
            metric: 'value_count',
            script: "doc.containsKey('{$field}') && !doc['{$field}'].empty ? 1 : 0",
            options: []
        );
    }

    /**
     * Terms needs field mapping lookup
     *
     * @throws BuilderException
     */
    protected function compileTermsAggregation(Builder $builder, array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['key'];
        $indexableField = $this->getIndexableField($field, $builder);

        $options = ['size' => $builder->getLimit()];

        if (is_array($aggregation['args'])) {
            $options = array_merge($options, DslFactory::filterTermsAggregationOptions($aggregation['args']));
        }

        return DslFactory::termsAggregation($indexableField, $builder->getLimit(), $options);
    }

    /**
     * Date histogram has complex interval and bounds handling
     */
    protected function compileDateHistogramAggregation(array $aggregation): array
    {
        $args = $aggregation['args'];
        $field = is_array($args) ? $args['field'] : $args;
        $options = $aggregation['options'] ?? [];

        $fixedInterval = $args['fixed_interval'] ?? null;
        $calendarInterval = $args['calendar_interval'] ?? null;
        $minDocCount = $args['min_doc_count'] ?? null;
        $extendedBounds = null;

        if (isset($args['extended_bounds']) && is_array($args['extended_bounds'])) {
            $extendedBounds = [
                'min' => $this->convertDateTime($args['extended_bounds'][0]),
                'max' => $this->convertDateTime($args['extended_bounds'][1]),
            ];
        }

        return DslFactory::dateHistogram($field, $fixedInterval, $calendarInterval, $minDocCount, $extendedBounds, $options);
    }

    /**
     * Filter aggregation needs where clause compilation
     */
    protected function compileFilterAggregation(mixed $args): array
    {
        $filter = $this->compileWheres($args);

        return DslFactory::filterAggregation(array_merge($filter['query'] ?? [], $filter['filter'] ?? []));
    }
}

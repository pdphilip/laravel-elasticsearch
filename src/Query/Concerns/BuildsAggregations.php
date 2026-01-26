<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PDPhilip\Elasticsearch\Exceptions\BuilderException;
use PDPhilip\Elasticsearch\Utils\Sanitizer;

/**
 * Builds aggregation queries for Elasticsearch.
 *
 * @property \PDPhilip\Elasticsearch\Connection $connection
 * @property \PDPhilip\Elasticsearch\Query\Grammar\Grammar $grammar
 * @property \PDPhilip\Elasticsearch\Query\Processor\Processor $processor
 * @property array $bucketAggregations
 * @property array $metricsAggregations
 * @property mixed $asDsl
 * @property string|null $from
 *
 * @mixin \PDPhilip\Elasticsearch\Query\Builder
 */
trait BuildsAggregations
{
    // ----------------------------------------------------------------------
    // Bucket Aggregations
    // ----------------------------------------------------------------------

    /**
     * Adds a bucket aggregation to the current query.
     *
     * @param  string  $key  The key for the bucket.
     * @param  string|null  $type  The type of aggregation.
     * @param  mixed|null  $args  The arguments for the aggregation.
     *                            Can be a callable to generate the arguments using a new query.
     * @param  mixed|null  $aggregations  The sub-aggregations or nested aggregations.
     *                                    Can be a callable to generate them using a new query.
     * @return self The current query builder instance.
     */
    public function bucket($key, $type = null, $args = null, $aggregations = null): self
    {
        return $this->bucketAggregation($key, $type, $args, $aggregations);
    }

    /**
     * Adds a bucket aggregation to the current query.
     *
     * @param  string  $key  The key for the bucket.
     * @param  string|null  $type  The type of aggregation.
     * @param  mixed|null  $args  The arguments for the aggregation.
     *                            Can be a callable to generate the arguments using a new query.
     * @param  mixed|null  $aggregations  The sub-aggregations or nested aggregations.
     *                                    Can be a callable to generate them using a new query.
     * @return self The current query builder instance.
     */
    public function bucketAggregation($key, $type = null, $args = null, $aggregations = null): self
    {
        if (! is_string($args) && is_callable($args)) {
            $args = call_user_func($args, $this->newQuery());
        }

        if (! is_string($aggregations) && is_callable($aggregations)) {
            $aggregations = call_user_func($aggregations, $this->newQuery());
        }

        $this->bucketAggregations[] = compact(
            'key',
            'type',
            'args',
            'aggregations'
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function groupBy(...$groups)
    {
        $groups = Sanitizer::cleanArrayValues($groups);

        $this->bucketAggregation('group_by', 'composite', function ($query) use ($groups) {
            $query->from = $this->from;

            return collect($groups)->map(function ($group) use ($query) {
                return [$group => ['terms' => ['field' => $query->grammar->getIndexableField($group, $query)]]];
            })->toArray();
        });

        return $this;
    }

    public function groupByRanges($column, $ranges = [])
    {
        $args = [
            'field' => $column,
            'ranges' => $ranges,
        ];
        $key = $column.'_range';

        return $this->bucketAggregation($key, 'range', $args);
    }

    public function groupByDateRanges($column, $ranges = [], $options = [])
    {
        $args = [
            'field' => $column,
            'ranges' => $ranges,
            'options' => $options,
        ];
        $key = $column.'_range';

        return $this->bucketAggregation($key, 'date_range', $args);
    }

    // ----------------------------------------------------------------------
    // Metric Aggregations
    // ----------------------------------------------------------------------

    public function agg(array $functions, string|array $columns, array $options = [])
    {
        return $this->aggregateMultiMetric($functions, $columns, $options);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string|array  $function
     * @param  \Illuminate\Database\Query\Expression|string|array  $columns
     */
    public function aggregate($function, $columns = ['*'], $options = [])
    {
        if (is_array($function)) {
            return $this->aggregateMultiMetric($function, $columns, $options);
        }

        return $this->aggregateMetric($function, $columns, $options);
    }

    /**
     * {@inheritdoc}
     *
     * @param  \Illuminate\Database\Query\Expression|string|array  $column
     */
    public function min($column, array $options = [])
    {
        return $this->aggregate(__FUNCTION__, Arr::wrap($column), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function max($column, array $options = [])
    {
        return $this->aggregate(__FUNCTION__, Arr::wrap($column), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function sum($column, array $options = [])
    {
        $result = $this->aggregate(__FUNCTION__, Arr::wrap($column), $options);

        return $result ?: 0;
    }

    /**
     * {@inheritdoc}
     */
    public function avg($column, array $options = [])
    {
        return $this->aggregate(__FUNCTION__, Arr::wrap($column), $options);
    }

    /**
     * A boxplot metrics aggregation that computes boxplot of numeric values extracted from the aggregated documents.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-boxplot-aggregation.html
     *
     * @param  \Illuminate\Database\Query\Expression|string|array  $columns
     * @param  array  $options
     */
    public function boxplot($columns, $options = [])
    {
        $result = $this->aggregate('boxplot', Arr::wrap($columns), $options);

        return $result ?: [];
    }

    /**
     * Retrieve the Cardinality Stats of the values of a given keyword column.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-cardinality-aggregation.html
     *
     * @param  \Illuminate\Database\Query\Expression|string|array  $columns
     * @param  array  $options
     */
    public function cardinality($columns, $options = [])
    {
        $result = $this->aggregate(__FUNCTION__, Arr::wrap($columns), $options);

        return $result ?: [];
    }

    /**
     * Retrieve the extended stats of the values of a given column.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-extendedstats-aggregation.html
     *
     * @param  \Illuminate\Database\Query\Expression|string|array  $columns
     * @param  array  $options
     */
    public function extendedStats($columns, $options = [])
    {
        $result = $this->aggregate('extended_stats', Arr::wrap($columns), $options);

        return $result ?: [];
    }

    /**
     * Retrieve the String Stats of the values of a given keyword column.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-string-stats-aggregation.html
     *
     * @param  \Illuminate\Database\Query\Expression|string|array  $columns
     * @param  array  $options
     */
    public function matrix($columns, $options = [])
    {
        $result = $this->aggregate('matrix_stats', Arr::wrap($columns), $options);

        return $result ?: [];
    }

    /**
     * Retrieve the median absolute deviation stats of the values of a given column.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-median-absolute-deviation-aggregation.html
     *
     * @param  \Illuminate\Database\Query\Expression|string|array  $columns
     * @param  array  $options
     */
    public function medianAbsoluteDeviation($columns, $options = [])
    {
        $result = $this->aggregate('median_absolute_deviation', Arr::wrap($columns), $options);

        return $result ?: [];
    }

    /**
     * Retrieve the percentiles of the values of a given column.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-percentile-aggregation.html
     *
     * @param  \Illuminate\Database\Query\Expression|string|array  $columns
     * @param  array  $options
     */
    public function percentiles($columns, $options = [])
    {
        $result = $this->aggregate('percentiles', Arr::wrap($columns), $options);

        return $result ?: [];
    }

    /**
     * Retrieve the stats of the values of a given column.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-stats-aggregation.html
     *
     * @param  \Illuminate\Database\Query\Expression|string|array  $columns
     * @param  array  $options
     */
    public function stats($columns, $options = [])
    {
        $result = $this->aggregate(__FUNCTION__, Arr::wrap($columns), $options);

        return $result ?: [];
    }

    /**
     * Retrieve the String Stats of the values of a given keyword column.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-string-stats-aggregation.html
     *
     * @param  \Illuminate\Database\Query\Expression|string|array  $columns
     * @param  array  $options
     */
    public function stringStats($columns, $options = [])
    {
        $result = $this->aggregate('string_stats', Arr::wrap($columns), $options);

        return $result ?: [];
    }

    // ----------------------------------------------------------------------
    // Aggregation Results
    // ----------------------------------------------------------------------

    /**
     * Get the aggregations returned from query
     */
    public function getAggregationResults(): array|Collection
    {
        $this->getResultsOnce();

        return $this->processor->getAggregationResults();
    }

    /**
     * Get the raw aggregations returned from query
     */
    public function getRawAggregationResults(): array
    {
        $this->getResultsOnce();

        return $this->processor->getRawAggregationResults();
    }

    public function getGroupByAfterKey($offset): mixed
    {
        $clone = clone $this;
        $clone->limit = $offset;
        $clone->offset = 0;
        $res = collect($clone->getRaw());

        return $res->pull('aggregations.group_by.after_key');
    }

    // ----------------------------------------------------------------------
    // Internal Aggregation Methods
    // ----------------------------------------------------------------------

    /**
     * @return array|mixed
     *
     * @throws BuilderException
     */
    protected function aggregateMetric($function, $columns = ['*'], $options = [])
    {
        if ($function == 'matrix_stats' && is_array($columns)) {
            $args = $columns;
        }
        // Each column we want aggregated
        $columns = Arr::wrap($columns);
        foreach ($columns as $column) {
            $this->metricsAggregations[] = [
                'key' => $column,
                'args' => ! empty($args) ? $args : $column,
                'type' => $function,
                'options' => $options,
            ];
        }
        if ($this->asDsl) {
            return $this->grammar->compileSelect($this);
        }

        return $this->processor->processAggregations($this, $this->connection->select($this->grammar->compileSelect($this), []));
    }

    protected function aggregateMultiMetric(array $functions, string|array $columns, $options = [])
    {
        // Each column we want aggregated
        if (! is_array($columns)) {
            $columns = Arr::wrap($columns);
        }
        foreach ($columns as $column) {
            foreach ($functions as $function) {
                $this->metricsAggregations[] = [
                    'key' => $function.'_'.$column,
                    'args' => $column,
                    'type' => $function,
                    'options' => $options,
                ];
            }
        }
        if ($this->asDsl) {
            return $this->grammar->compileSelect($this);
        }

        return $this->processor->processAggregations($this, $this->connection->select($this->grammar->compileSelect($this), []));
    }
}

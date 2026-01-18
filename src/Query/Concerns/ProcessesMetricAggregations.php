<?php

namespace PDPhilip\Elasticsearch\Query\Concerns;

trait ProcessesMetricAggregations
{
    public function processMetricAggregations($rawAgg)
    {
        if (! $this->query->metricsAggregations) {
            return [];
        }
        $result = [];
        foreach ($this->query->metricsAggregations as $metricsAggregation) {
            $result = [
                ...$result,
                ...$this->processMetricAggregation($metricsAggregation, $rawAgg),
            ];
        }
        // Single metric agg
        if (count($this->query->metricsAggregations) == 1) {
            $key = array_key_first($result);

            return $result[$key];
        }

        return $result;

    }

    public function processMetricAggregation($metricsAggregation, $rawAgg)
    {
        $key = $metricsAggregation['key'];
        $type = $metricsAggregation['type'];
        $cleanKey = str_replace($type.'_', '', $key);
        $result = $this->extractAggResult($type, $rawAgg, $key);

        return ["{$type}_{$cleanKey}" => $result];
    }

    protected function extractAggResult($type, $rawAgg, $key)
    {
        return match ($type) {
            'count', 'avg', 'max', 'min', 'sum', 'median_absolute_deviation', 'value_count', 'cardinality' => $rawAgg[$key]['value'],
            'percentiles' => $rawAgg[$key]['values'],
            'matrix_stats' => $this->extractMatrixResult($rawAgg, $key),
            'extended_stats', 'stats', 'string_stats', 'boxplot' => $rawAgg[$key],
        };
    }

    protected function extractMatrixResult($rawAgg, $key): ?array
    {
        $results = collect($rawAgg[$key]['fields']);

        return $results->where('name', $key)->first();
    }
}

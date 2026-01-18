<?php

namespace PDPhilip\Elasticsearch\Query\Concerns;

trait ProcessesBucketAggregations
{
    public function processBucketAggregations($bucketAggregations, $rawAggs): array
    {
        $result = [];
        foreach ($bucketAggregations as $bucketAggregation) {
            $result = [...$result, ...$this->parseBucket($bucketAggregation, $rawAggs)];
        }

        return $result;
    }

    protected function parseBucket($bucketAggregation, $rawAggs)
    {
        $key = $bucketAggregation['key'];

        if (! isset($rawAggs[$key]['buckets'])) {
            return $rawAggs[$key];
        }

        $result = collect($rawAggs[$key]['buckets'])->map(function ($bucket) use ($key) {
            $metricAggs = $this->appendMetricsToBucket($bucket);
            // ES is super annoying with how it does keys. For composite, it returns keys as an array but in other cases it does not.
            if (! is_array($bucket['key'])) {
                $bucket['key'] = [$key => $bucket['key']];
            }

            return [
                ...$bucket['key'],
                ...$metricAggs,
                '_meta' => $this->metaFromResult(['doc_count' => $bucket['doc_count']]),
            ];

        });

        return $result->toArray();
    }

    protected function appendMetricsToBucket($bucket)
    {
        if (! $this->query->metricsAggregations) {
            return [];
        }
        $result = [];
        foreach ($this->query->metricsAggregations as $metricsAggregation) {
            $result = [
                ...$result,
                ...$this->processMetricAggregation($metricsAggregation, $bucket),
            ];
        }

        return $result;
    }
}

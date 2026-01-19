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
        $type = $bucketAggregation['type'] ?? null;

        if (! isset($rawAggs[$key]['buckets'])) {
            return $rawAggs[$key];
        }
        $result = collect($rawAggs[$key]['buckets'])->map(function ($bucket) use ($key, $type) {
            $metricAggs = $this->appendMetricsToBucket($bucket);

            if ($type === 'range') {
                return $this->makeRangeBucket($bucket, $metricAggs);
            }

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

    protected function makeRangeBucket($bucket, $metricAggs)
    {
        $cleanBucket = [
            'key' => $bucket['key'],
            'from' => $bucket['from'] ?? null,
            'to' => $bucket['to'] ?? null,
        ];
        $docCount = $bucket['doc_count'] ?? 0;
        unset($bucket['doc_count']);
        $cleanBucket['count'] = $docCount;

        return [
            ...$cleanBucket,
            ...$metricAggs,
            '_meta' => $this->metaFromResult(['doc_count' => $docCount]),
        ];
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

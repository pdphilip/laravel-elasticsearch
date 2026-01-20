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

            if (in_array($type, ['range', 'date_range', 'ip_range'])) {
                return $this->unpackRangeBucket($key, $bucket, $metricAggs);
            }

            if (! is_array($bucket['key'])) {
                $bucket['key'] = [$key => $bucket['key']];
            }

            return [
                ...$bucket['key'],
                ...$metricAggs,
                '_meta' => $this->metaFromResult(['doc_count' => $bucket['doc_count'], 'bucket' => $bucket]),
            ];

        });

        return $result->toArray();
    }

    protected function unpackRangeBucket($key, $bucket, $metricAggs)
    {
        $recordKey = $key.'_'.$bucket['key'];
        $aggs['count_'.$recordKey] = $bucket['doc_count'];
        if ($metricAggs) {
            foreach ($metricAggs as $metricAgg => $value) {
                $aggs[$metricAgg.'_'.$recordKey] = $value;
            }
        }

        $docCount = $bucket['doc_count'] ?? 0;

        return [
            ...$aggs,
            '_meta' => $this->metaFromResult(['doc_count' => $docCount, 'bucket' => $bucket]),
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

<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query\Processor\Concerns;

use Illuminate\Support\Collection;

trait ProcessesDistinctAggregations
{
    public function processDistinctAggregations($index, $result, $columns, $withCount): Collection
    {
        $this->pullAfterKey($result);
        $keys = [];
        foreach ($columns as $column) {
            $keys[] = 'by_'.$column;
        }

        // Unwrap nested/filter agg wrappers if the expected key isn't at the top level
        $aggs = $result['aggregations'];
        if (! empty($keys[0]) && ! isset($aggs[$keys[0]])) {
            $aggs = $this->unwrapNestedAggregation($aggs, $keys[0]);
        }

        $aggregations = $this->parseDistinctBucket($columns, $keys, $aggs, 0, $withCount);
        $aggregations = collect($aggregations);

        return $aggregations->map(function ($aggregation) use ($index) {
            return $this->liftToMeta($aggregation, ['_index' => $index], ['doc_count', 'bucket']);
        });
    }

    public function processBulkDistinctAggregations($index, $result, $columns, $withCount): Collection
    {
        $this->pullAfterKey($result);
        $aggregations = [];
        foreach ($columns as $column) {
            $keys = ['by_'.$column];

            // Each column's agg may be inside its own nested wrapper
            $aggs = $result['aggregations'];
            if (! isset($aggs[$keys[0]])) {
                $aggs = $this->unwrapNestedAggregation($aggs, $keys[0]);
            }

            $aggregations = [
                ...$aggregations,
                ...$this->parseDistinctBucket([$column], $keys, $aggs, 0, $withCount),
            ];
        }
        $aggregations = collect($aggregations);

        return $aggregations->map(function ($aggregation) use ($index) {

            return $this->liftToMeta($aggregation, ['_index' => $index], ['doc_count', 'bucket']);
        });
    }

    protected function parseDistinctBucket($columns, $keys, $response, $index, $includeDocCount, $currentData = []): array
    {
        $data = [];
        if (! empty($response[$keys[$index]]['buckets'])) {
            foreach ($response[$keys[$index]]['buckets'] as $res) {

                $datum = $currentData;

                $col = $columns[$index];

                $datum['doc_count'] = $res['doc_count'];
                $datum['bucket'] = $res;
                $datum[$col] = $res['key'];

                if ($includeDocCount) {
                    $datum[$col.'_count'] = $res['doc_count'];
                }

                if (isset($columns[$index + 1])) {
                    $nestedData = $this->parseDistinctBucket($columns, $keys, $res, $index + 1, $includeDocCount, $datum);
                    $data = [...$data, ...$nestedData];
                } else {
                    $data[] = $datum;
                }
            }
        }

        return $data;
    }

    protected function pullAfterKey($result)
    {
        if (! empty($result['hits']['hits']) && is_array($result['hits']['hits'])) {
            $last = collect($result['hits']['hits'])->last();
            if (! empty($last['sort'])) {
                $this->query->getMetaTransfer()->set('after_key', $last['sort']);
            }
        }
    }
}

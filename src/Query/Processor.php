<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query;

use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;
use Illuminate\Support\Collection;
use PDPhilip\Elasticsearch\Data\MetaDTO;
use PDPhilip\Elasticsearch\Helpers\Sanitizer;

class Processor extends BaseProcessor
{
    protected $rawResponse;

    protected $rawAggregations;

    protected $aggregations;

    protected $query;

    /**
     * Get the raw aggregation results
     */
    public function getAggregationResults(): array|Collection
    {
        return $this->aggregations;
    }

    /**
     * Get the raw aggregation results
     */
    public function getRawAggregationResults(): array
    {
        return $this->rawAggregations;
    }

    /**
     * Get the raw Elasticsearch response as an array
     */
    public function getRawResponse(): array
    {
        return is_array($this->rawResponse) ? $this->rawResponse : $this->rawResponse->asArray();
    }

    public function processInsertGetId(Builder|BaseBuilder $query, $sql, $values, $sequence = null)
    {
        $result = $query->getConnection()->insert($sql, $values);
        $this->rawResponse = $result;

        $last = collect($this->getRawResponse()['items'])->last();

        return $last['index']['_id'] ?? null;
    }

    public function processDistinctAggregations($result, $columns, $withCount): Collection
    {
        $keys = [];
        foreach ($columns as $column) {
            $keys[] = 'by_'.$column;
        }

        return collect($this->parseDistinctBucket($columns, $keys, $result['aggregations'], 0, $withCount));
    }

    protected function parseDistinctBucket($columns, $keys, $response, $index, $includeDocCount, $currentData = []): array
    {
        $data = [];
        if (! empty($response[$keys[$index]]['buckets'])) {
            foreach ($response[$keys[$index]]['buckets'] as $res) {

                $datum = $currentData;

                $col = $columns[$index];

                $datum['doc_count'] = $res['doc_count'];
                $datum[$col] = $res['key'];

                if ($includeDocCount) {
                    $datum[$col.'_count'] = $res['doc_count'];
                }

                if (isset($columns[$index + 1])) {
                    $nestedData = $this->parseDistinctBucket($columns, $keys, $res, $index + 1, $includeDocCount, $datum);

                    if (! empty($nestedData)) {
                        $data = array_merge($data, $nestedData);
                    } else {
                        $data[] = $datum;
                    }
                } else {
                    $data[] = $datum;
                }
            }
        }

        return $data;
    }

    public function processAggregations(Builder $query, $result)
    {
        $this->rawResponse = $result;
        $this->query = $query;
        $response = $this->getRawResponse();
        $this->rawAggregations = $response['aggregations'] ?? [];
        if (! empty($response['aggregations']['group_by']['after_key'])) {
            $this->query->getMetaTransfer()->set('after_key', $response['aggregations']['group_by']['after_key']);
        }

        $result = [];
        if (! empty($this->query->bucketAggregations)) {
            foreach ($this->query->bucketAggregations as $bucketAggregation) {
                // I love me the spread operator...
                $result = [...$result, ...$this->processBucketAggregation($bucketAggregation)];
            }
            $this->aggregations = collect($result);
        } else {
            // No buckets so it's likely all metrics
            $result = $this->processMetricAggregations($this->rawAggregations);
            $this->aggregations = $result;
        }

        return $this->aggregations;
    }

    public function processBucketAggregation($bucketAggregation)
    {
        $key = $bucketAggregation['key'];

        if (! isset($this->rawAggregations[$key]['buckets'])) {
            return $this->rawAggregations[$key];
        }

        return collect($this->rawAggregations[$key]['buckets'])->map(function ($bucket) use ($key) {

            $metricAggs = $this->processMetricAggregations($bucket, true);
            // ES is super annoying with how it does keys. For composite it returns keys as array but in other cases it does not.
            if (! is_array($bucket['key'])) {
                $bucket['key'] = [$key => $bucket['key']];
            }

            return [
                ...$bucket['key'],
                ...$metricAggs,
                '_meta' => $this->metaFromResult(['doc_count' => $bucket['doc_count']]),
            ];

        })->toArray();
    }

    public function processMetricAggregations($bucket, $withinBucket = false)
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
        // Single metric agg
        if (! $withinBucket && count($this->query->metricsAggregations) == 1) {
            $key = array_key_first($result);

            return $result[$key];
        }
        if (isset($result['distinct'])) {
            $result = $result['distinct'];
        }

        return $result;

    }

    public function processMetricAggregation($metricsAggregation, $bucket)
    {
        $key = $metricsAggregation['key'];
        $type = $metricsAggregation['type'];
        $cleanKey = str_replace($type.'_', '', $key);

        if ($this->query->distinct) {
            return ['distinct' => $this->processDistinctAggregation($bucket, $type)];
        }

        $result = $this->extractAggResult($type, $bucket, $key);

        return ["{$type}_{$cleanKey}" => $result];
    }

    protected function extractAggResult($type, $bucket, $key)
    {
        return match ($type) {
            'count', 'avg', 'max', 'min', 'sum', 'median_absolute_deviation', 'value_count', 'cardinality' => $bucket[$key]['value'],
            'percentiles' => $bucket[$key]['values'],
            'matrix_stats' => $this->extractMatrixResult($bucket, $key),
            'extended_stats', 'stats', 'string_stats', 'boxplot' => $bucket[$key],
        };
    }

    protected function extractMatrixResult($bucket, $key): ?array
    {
        $results = collect($bucket[$key]['fields']);

        return $results->where('name', $key)->first();
    }

    public function processDistinctAggregation($result, $metric)
    {
        return collect($this->parseDistinctBucketWithMetrics($result, $metric));
    }

    protected function parseDistinctBucketWithMetrics($response, $metric, $currentData = []): array
    {
        $data = [];

        foreach ($response as $aggKey => $aggData) {
            if (! isset($aggData['buckets']) || ! is_array($aggData['buckets'])) {
                continue; // Skip non-bucket fields
            }

            foreach ($aggData['buckets'] as $res) {
                $datum = $currentData;
                $datum['doc_count'] = $res['doc_count'] ?? 0;
                $datum[$aggKey] = $res['key'] ?? null;

                $hasMetric = false;

                foreach ($res as $key => $value) {
                    if (is_array($value) && strpos($key, "{$metric}_") === 0) {
                        $datum[$key] = $this->extractAggResult($metric, $res, $key);
                        $hasMetric = true;
                    }
                }

                $nestedAdded = false;
                $nestedData = [];

                foreach ($res as $nestedKey => $nestedValue) {
                    if (is_array($nestedValue) && isset($nestedValue['buckets'])) {
                        $nestedData = $this->parseDistinctBucketWithMetrics([$nestedKey => $nestedValue], $metric, $datum);
                        if (! empty($nestedData)) {
                            $nestedAdded = true;
                        }
                    }
                }

                if ($nestedAdded) {
                    $data = array_merge($data, $nestedData);
                } elseif ($hasMetric) {
                    $data[] = $datum;
                }
            }
        }

        return $data;
    }

    /**
     * Process the results of a "select" query.
     *
     * @param  Elasticsearch  $results
     */
    public function processSelect(BaseBuilder|Builder $query, $results): array|Collection
    {
        $this->rawResponse = $results;
        $this->query = $query;
        $response = $this->getRawResponse();
        $queryMeta = $this->metaFromResult([
            'query' => 'select',
            'dsl' => $query->toSql(),
        ]);
        $query->setMetaTransfer($queryMeta);

        $documents = collect();

        if ($this->query->distinct) {
            $query->getMetaTransfer()->set('query', 'distinct');
            $index = $query->inferIndex();
            $aggregations = $this->processDistinctAggregations($response, $query->columns, $query->distinctCount ?? false);
            $documents = $aggregations->map(function ($agg) use ($index) {
                return $this->liftToMeta($agg, ['_index' => $index], ['doc_count']);
            });

            $query->getMetaTransfer()->set('total', $documents->count());

            return $documents->all();
        }

        $this->aggregations = $response['aggregations'] ?? [];
        if ($this->aggregations) {

            return $this->processAggregations($query, $results);
        }
        $lastSort = null;
        foreach ($response['hits']['hits'] as $results) {
            $documents->add($this->documentFromResult($this->query, $results));
            if (! empty($results['sort'][0])) {
                $lastSort = $results['sort'];
            }
        }
        $query->getMetaTransfer()->set('total', count($documents));
        $query->getMetaTransfer()->set('after_key', $lastSort);

        return $documents->all();
    }

    /**
     * Create a document from the given result
     */
    public function documentFromResult(Builder $query, array $result): array
    {

        $document = $result['_source'];
        $document['_id'] = $result['_id'];
        $meta = ['_index' => $result['_index'], '_score' => $result['_score'] ?? null];
        if (! empty($result['highlight'])) {
            $meta['highlight'] = Sanitizer::clearKeywordsFromHighlights($result['highlight']);
        }

        $document['_meta'] = $this->metaFromResult($meta);
        if (isset($result['inner_hits'])) {
            $document = $this->addInnerHitsToDocument($document, $result['inner_hits']);
        }

        return $document;
    }

    /**
     * Create document meta from the given result
     */
    public function metaFromResult(array $extra = []): MetaDTO
    {
        return MetaDTO::make($this->getRawResponse(), $extra);
    }

    public function liftToMeta($data, $baseValues, $keys = [])
    {
        $meta = MetaDTO::make($baseValues);
        foreach ($keys as $key) {
            $meta->set($key, $data[$key]);
            unset($data[$key]);
        }
        $data['_meta'] = $meta;

        return $data;
    }

    /**
     * Add inner hits to a document
     *
     * @param  array  $document
     * @param  array  $innerHits
     */
    protected function addInnerHitsToDocument($document, $innerHits): array
    {
        foreach ($innerHits as $documentType => $hitResults) {
            unset($document[$documentType]);
            foreach ($hitResults['hits']['hits'] as $result) {
                $document[$documentType][] = $result['_source'];
            }
        }

        return $document;
    }

    /**
     * Process the results of a tables query.
     *
     * @param  array|Elasticsearch  $results
     * @return array
     */
    public function processTables($results)
    {
        return collect(is_array($results) ? $results : $results->asArray())->map(function ($result) {
            return [
                'name' => $result['index'],
                'status' => $result['status'] ?? null,
                'health' => $result['health'] ?? null,
                'uuid' => $result['uuid'] ?? null,
                'docs_count' => $result['docs.count'] ?? 0,
                'docs_deleted' => $result['docs.deleted'] ?? 0,
                'store_size' => $result['store.size'] ?? 0,
            ];
        })->all();
    }

    /**
     *  Process the results of an update query.
     */
    public function processUpdate(Builder $query, Elasticsearch $result): int
    {
        $this->rawResponse = $result;
        $this->query = $query;

        return $this->getRawResponse()['updated'];
    }

    /**
     * Process the results of a delete query.
     */
    public function processDelete(Builder $query, Elasticsearch $result): bool
    {
        $this->rawResponse = $result;
        $this->query = $query;

        return ! empty($this->getRawResponse()['deleted']);
    }

    /**
     *  Process the results of an insert query.
     */
    public function processInsert(Builder $query, Elasticsearch $result): bool
    {
        $this->rawResponse = $result;
        $this->query = $query;

        return ! $this->getRawResponse()['errors'];
    }

    public function processRaw($query, $response)
    {
        $this->rawResponse = $response;
        $documents = collect();
        foreach ($response['hits']['hits'] as $results) {
            $documents->add($this->documentFromResult($query, $results));
        }

        return $documents->all();
    }
}

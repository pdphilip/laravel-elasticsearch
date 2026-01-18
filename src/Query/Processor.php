<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query;

use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;
use Illuminate\Support\Collection;
use PDPhilip\Elasticsearch\Data\MetaDTO;
use PDPhilip\Elasticsearch\Utils\Sanitizer;

class Processor extends BaseProcessor
{
    use Concerns\ProcessesBucketAggregations;
    use Concerns\ProcessesDistinctAggregations;
    use Concerns\ProcessesMetricAggregations;

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

    public function processAggregations(Builder $query, $result)
    {
        $this->rawResponse = $result;
        $this->query = $query;
        $response = $this->getRawResponse();
        $this->rawAggregations = $response['aggregations'] ?? [];
        if (! empty($response['aggregations']['group_by']['after_key'])) {
            $this->query->getMetaTransfer()->set('after_key', $response['aggregations']['group_by']['after_key']);
        }

        if (! empty($this->query->bucketAggregations)) {
            $result = $this->processBucketAggregations($this->query->bucketAggregations, $this->rawAggregations);
            $this->aggregations = collect($result);
        } else {
            // No buckets, so it's likely all metrics
            $result = $this->processMetricAggregations($this->rawAggregations);
            $this->aggregations = $result;
        }

        return $this->aggregations;
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
            $documents = $this->processDistinctAggregations($index, $response, $query->columns, $query->distinctCount ?? false);
            $query->getMetaTransfer()->set('total', $documents->count());

            return $documents->all();
        }
        // @phpstan-ignore-next-line
        //        if ($this->query->bulkDistinct) {
        //            $query->getMetaTransfer()->set('query', 'bulkDistinct');
        //            $index = $query->inferIndex();
        //            $documents = $this->processDistinctAggregations($index, $response, $query->columns, $query->distinctCount ?? false);
        //            $query->getMetaTransfer()->set('total', $documents->count());
        //
        //            return $documents->all();
        //        }

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

    public function processBulkInsert(Builder $query, Elasticsearch $result): array
    {
        $this->rawResponse = $result;
        $this->query = $query;

        $process = $result->asArray();

        $outcome = [
            'hasErrors' => $process['errors'],
            'total' => count($process['items']),
            'took' => $process['took'],
            'success' => 0,
            'created' => 0,
            'modified' => 0,
            'failed' => 0,
            'errors' => [],
        ];
        if (! empty($process['items'])) {
            foreach ($process['items'] as $item) {
                if (! empty($item['index']['error'])) {
                    $outcome['errors'][] = [
                        'id' => $item['index']['_id'] ?? null,
                        'type' => $item['index']['error']['type'] ?? null,
                        'reason' => $item['index']['error']['reason'] ?? null,
                    ];
                    $outcome['failed']++;
                } else {
                    $outcome['success']++;
                    if ($item['index']['status'] == 201) {
                        $outcome['created']++;
                    } else {
                        $outcome['modified']++;
                    }
                }
            }
        }

        return $outcome;
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

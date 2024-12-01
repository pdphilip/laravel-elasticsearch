<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query;

use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Data\Meta;
use PDPhilip\Elasticsearch\Exceptions\RuntimeException;

class Processor extends BaseProcessor
{

  protected $rawResponse;
  protected $rawAggregations;
  protected $aggregations;
  protected $query;

  /**
   * Get the raw aggregation results
   *
   * @param array
   */
  public function getAggregationResults(): array
  {
    return $this->aggregations;
  }

  /**
   * Get the raw aggregation results
   *
   * @param array
   */
  public function getRawAggregationResults(): array
  {
    return $this->rawAggregations;
  }

  /**
   * Get the raw Elasticsearch response as an array
   *
   * @return array
   */
  public function getRawResponse(): array
  {
    return is_array($this->rawResponse) ? $this->rawResponse : $this->rawResponse->asArray();
  }

  public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
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
    $this->rawAggregations = $this->getRawResponse()['aggregations'] ?? [];

    $result = [];

    if(!empty($this->query->bucketAggregations)){
      foreach ($this->query->bucketAggregations as $bucketAggregation){
        // I love me the spread operator...
        $result = [...$result, ...$this->processBucketAggregation($bucketAggregation)];
      }
    } else {
      // No buckets so it's likely all metrics
      $result = $this->processMetricAggregations($this->rawAggregations);
    }

    $this->aggregations = $result;

    return $this->aggregations;
  }

  public function processBucketAggregation($bucketAggregation)
  {

    $key = $bucketAggregation['key'];

    if(!isset($this->rawAggregations[$key]['buckets'])){
      return $this->rawAggregations[$key];
    }

    return collect($this->rawAggregations[$key]['buckets'])->map(function ($bucket) use ($key) {

      $metricAggs = $this->processMetricAggregations($bucket);

      // ES is super annoying with how it does keys. For composite it returns keys as array but in other cases it does not.
      if(!is_array($bucket['key'])){
        $bucket['key'] = [$key => $bucket['key']];
      }

      return [
        ...$bucket['key'],
        ...$metricAggs,
        '_meta' =>  $this->metaFromResult(['doc_count' => $bucket['doc_count']])
      ];

    })->toArray();
  }

  public function processMetricAggregations($bucket)
  {

    if(!$this->query->metricsAggregations){
      return [];
    }

    $result = [];
    foreach ($this->query->metricsAggregations as $metricsAggregation){
      $result = [
        ...$result,
        ...$this->processMetricAggregation($metricsAggregation, $bucket)
      ];
    }

    // Single metric agg
    if(count($this->query->metricsAggregations) == 1){
      $key = array_key_first($result);
      return $result[$key];
    }

    return $result;
  }

  public function processMetricAggregation($metricsAggregation, $bucket)
  {

    $key = $metricsAggregation['key'];
    $type = $metricsAggregation['type'];

    $result =  match ($type) {
      'count', 'avg', 'max', 'min', 'sum', 'median_absolute_deviation', 'value_count', 'cardinality' => $bucket[$key]['value'],
      'percentiles' => $bucket[$key]['values'],
      'matrix_stats' => $bucket[$key]['fields'][0],
      'extended_stats', 'stats', 'string_stats', 'boxplot' => $bucket[$key],
    };

    return ["{$type}_{$key}" => $result];
  }

  /**
   * Process the results of a "select" query.
   *
   * @return array
   */
  public function processSelect(Builder $query, $result)
  {

    $this->rawResponse = $result;
    $this->query = $query;

    $this->aggregations = $this->getRawResponse()['aggregations'] ?? [];

    if($this->aggregations){
      return $this->processAggregations($query, $result);
    }

    $documents = [];
    foreach ($this->getRawResponse()['hits']['hits'] as $result) {
      $documents[] = $this->documentFromResult($this->query, $result);
    }

    return $documents;
  }

  /**
   * Create a document from the given result
   *
   * @param  Builder $query
   * @param  array $result
   * @return array
   */
  public function documentFromResult(Builder $query, array $result): array
  {
    $document = $result['_source'];
    $document['_id'] = $result['_id'];

    $meta = ['_index' => $result['_index'], '_score' => $result['_score'] ?? null];
    if(!empty($result['highlight'])){
      $meta['highlight'] = $result['highlight'];
    }

    $document['_meta'] = $this->metaFromResult($meta);

    if (isset($result['inner_hits'])) {
      $document = $this->addInnerHitsToDocument($document, $result['inner_hits']);
    }

    return $document;
  }

  /**
   * Create document meta from the given result
   *
   * @param array $extra
   *
   * @return Meta
   */
  public function metaFromResult(array $extra = []): Meta
  {
    return Meta::make($this->getRawResponse(), $extra);
  }

  /**
   * Add inner hits to a document
   *
   * @param  array $document
   * @param  array $innerHits
   * @return array
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
   * @param  array  $results
   * @return array
   */
  public function processTables($results)
  {
    return collect($results->asArray())->map(function ($result){
      return [
        'name' => $result['index'],
        'status' => $result['status'] ?? null,
        'health' => $result['health'] ?? null,
        'uuid' => $result['uuid'] ?? null,
        'docs_count' => $result['docs.count'] ?? 0,
        'docs_deleted' => $result['docs.deleted'] ?? 0,
      ];
    })->all();
  }

  /**
   *  Process the results of an update query.
   *
   * @param Builder       $query
   * @param Elasticsearch $result
   *
   * @return int
   */
  public function processUpdate(Builder $query, Elasticsearch $result): int
  {
    $this->rawResponse = $result;
    $this->query = $query;

    return $this->getRawResponse()['updated'];
  }

  /**
   * Process the results of a delete query.
   *
   * @param Builder       $query
   * @param Elasticsearch $result
   *
   * @return bool
   */
  public function processDelete(Builder $query, Elasticsearch $result): bool
  {
    $this->rawResponse = $result;
    $this->query = $query;

    return ! empty($this->getRawResponse()['deleted']);
  }

  /**
   *  Process the results of an insert query.
   *
   * @param Builder       $query
   * @param Elasticsearch $result
   *
   * @return bool
   */
  public function processInsert(Builder $query, Elasticsearch $result): bool
  {
    $this->rawResponse = $result;
    $this->query = $query;

    return !$this->getRawResponse()['errors'];
  }

}

<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query;

use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;
use PDPhilip\Elasticsearch\Data\Meta;

class Processor extends BaseProcessor
{

  protected $rawResponse;
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
   * Get the raw Elasticsearch response as an array
   *
   * @return array
   */
  public function getRawResponse(): array
  {
    return $this->rawResponse->asArray();
  }

  public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
  {
    $result = $query->getConnection()->insert($sql, $values);
    $this->rawResponse = $result;

    $last = collect($this->getRawResponse()['items'])->last();
    return $last['index']['_id'] ?? null;
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

    $this->aggregations = $results['aggregations'] ?? [];

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
    $document['_meta'] = $this->metaFromResult(['_index' => $result['_index']]);

    if ($query->includeInnerHits && isset($result['inner_hits'])) {
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
      foreach ($hitResults['hits']['hits'] as $result) {
        $document['inner_hits'][$documentType][] = array_merge(['_id' => $result['_id']], $result['_source']);
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
    return collect((array) $results)->map(function ($result){
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

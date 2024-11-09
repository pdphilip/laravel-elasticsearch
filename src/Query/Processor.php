<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

class Processor extends BaseProcessor
{

  protected $rawResponse;

  protected $aggregations;

  /**
   * Process the results of a "select" query.
   *
   * @param Builder $query
   * @param array           $results
   *
   * @return array
   */
  public function processSelect(Builder $query, $results)
  {
    $this->rawResponse = $results;

    $this->aggregations = $results['aggregations'] ?? [];

    $this->query = $query;

    $documents = [];

    foreach ($results['hits']['hits'] as $result) {
      $documents[] = $this->documentFromResult($query, $result);
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

    if ($query->includeInnerHits && isset($result['inner_hits'])) {
      $document = $this->addInnerHitsToDocument($document, $result['inner_hits']);
    }

    return $document;
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
   * Get the raw Elasticsearch response
   *
   * @param array
   */
  public function getRawResponse(): array
  {
    return $this->rawResponse->asArray();
  }

  /**
   * Get the raw aggregation results
   *
   * @param array
   */
  public function getAggregationResults(): array
  {
    return $this->aggregations;
  }

  public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
  {
    $result = $query->getConnection()->insert($sql, $values);
    $result = $result->getRawResult();
    $last = collect($result['items'])->last();
    return $last['index']['_id'] ?? null;
  }

}

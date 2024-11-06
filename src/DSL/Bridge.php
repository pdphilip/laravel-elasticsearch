<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\DSL;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Exception;
use Illuminate\Support\Collection;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Contracts\ArrayStore;
use PDPhilip\Elasticsearch\DSL\exceptions\ParameterException;
use PDPhilip\Elasticsearch\DSL\exceptions\QueryException;
use PDPhilip\Elasticsearch\Enums\WaitFor;

class Bridge
{
    use IndexInterpreter, QueryBuilder;

    protected Connection $connection;

    protected ?Client $client;

    protected ?string $errorLogger;

    protected ?int $maxSize = 10; //ES default

    private ?string $index;

    private ?array $stashedMeta;

    private ?Collection $cachedKeywordFields = null;

    private ?string $indexPrefix;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->client = $this->connection->getClient();
        $this->index = $this->connection->getIndex();
        $this->maxSize = $this->connection->getMaxSize();
        $this->indexPrefix = $this->connection->getIndexPrefix();
        $this->errorLogger = $this->connection->getErrorLoggingIndex();
    }

    //======================================================================
    // PIT
    //======================================================================

    /**
     * @throws QueryException
     */
    public function processOpenPit($keepAlive = '5m'): string
    {
        $params = [
            'index' => $this->index,
            'keep_alive' => $keepAlive,

        ];
        $res = [];
        try {
            $process = $this->client->openPointInTime($params);
            $res = $process->asArray();
            if (empty($res['id'])) {
                throw new Exception('Error on PIT creation. No ID returned.');
            }
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $res['id'];
    }

    /**
     * @throws QueryException
     * @throws ParameterException
     */
    public function processPitFind($wheres, $options, $columns, $pitId, $searchAfter = false, $keepAlive = '5m'): Results
    {
        $params = $this->buildParams($this->index, $wheres, $options, $columns);
        unset($params['index']);

        $params['body']['pit'] = [
            'id' => $pitId,
            'keep_alive' => $keepAlive,
        ];
        if (empty($params['body']['sort'])) {
            $params['body']['sort'] = [];
        }
        //order catch by shard doc
        $params['body']['sort'][] = ['_shard_doc' => ['order' => 'asc']];

        if ($searchAfter) {
            $params['body']['search_after'] = $searchAfter;
        }
        $process = [];
        try {
            $process = $this->client->search($params);
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_sanitizePitSearchResponse($process, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * @throws QueryException
     */
    public function processClosePit($id): bool
    {
        $params = [
            'index' => $this->index,
            'body' => [
                'id' => $id,
            ],
        ];
        $res = [];
        try {
            $process = $this->client->closePointInTime($params);
            $res = $process->asArray();
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $res['succeeded'];
    }

    //======================================================================
    // BYO Query
    //======================================================================

    /**
     * @throws Exception
     */
    public function processSearchRaw($bodyParams, $returnRaw): Results
    {
        $params = [
            'index' => $this->index,
            'body' => $bodyParams,

        ];
        $process = [];
        try {
            $process = $this->client->search($params);
            if ($returnRaw) {
                return $this->_return($process->asArray(), [], $params, $this->_queryTag(__FUNCTION__));
            }
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_sanitizeSearchResponse($process, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * @throws QueryException
     */
    public function processAggregationRaw($bodyParams): Results
    {
        $params = [
            'index' => $this->index,
            'body' => $bodyParams,
        ];
        $process = [];
        try {
            $process = $this->client->search($params);
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_sanitizeRawAggsResponse($process, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * @throws QueryException
     */
    public function processIndicesDsl($method, $params): Results
    {
        $process = [];
        try {
            $process = $this->client->indices()->{$method}($params);
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_sanitizeSearchResponse($process, $params, $this->_queryTag(__FUNCTION__));
    }

    //======================================================================
    // To DSL
    //======================================================================

    /**
     * @throws QueryException
     * @throws ParameterException
     */
    public function processToDsl($wheres, $options, $columns): array
    {
        return $this->buildParams($this->index, $wheres, $options, $columns);
    }

    /**
     * @throws ParameterException
     * @throws QueryException
     */
    public function processToDslForSearch($searchParams, $searchOptions, $wheres, $opts, $fields, $cols): array
    {
        return $this->buildSearchParams($this->index, $searchParams, $searchOptions, $wheres, $opts, $fields, $cols);
    }

    //======================================================================
    // Find/Search Queries
    //======================================================================





    /**
     * @throws QueryException
     * @throws ParameterException
     */
    public function processSearch($searchParams, $searchOptions, $wheres, $opts, $fields, $cols): Results
    {
        $params = $this->buildSearchParams($this->index, $searchParams, $searchOptions, $wheres, $opts, $fields, $cols);

        return $this->_returnSearch($params, __FUNCTION__);
    }

    /**
     * @throws QueryException
     */
    protected function _returnSearch($params, $source): Results
    {
        if (empty($params['size'])) {
            $params['size'] = $this->maxSize;
        }
        $process = [];
        try {
            $process = $this->client->search($params);
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_sanitizeSearchResponse($process, $params, $this->_queryTag($source));
    }

    //----------------------------------------------------------------------
    // Distinct
    //----------------------------------------------------------------------
    /**
     * @throws QueryException
     * @throws ParameterException
     */
    public function processDistinct($wheres, $options, $columns, $includeDocCount = false): Results
    {
        if ($columns && ! is_array($columns)) {
            $columns = [$columns];
        }
        $sort = $options['sort'] ?? [];
        $skip = $options['skip'] ?? 0;
        $limit = $options['limit'] ?? 0;
        unset($options['sort']);
        unset($options['skip']);
        unset($options['limit']);

        if ($sort) {
            $sortField = key($sort);
            $sortDir = $sort[$sortField]['order'] ?? 'asc';
            $sort = [$sortField => $sortDir];
        }

        $params = $this->buildParams($this->index, $wheres, $options);
        $data = [];
        $response = [];
        try {
            $params['body']['aggs'] = $this->createNestedAggs($columns, $sort);
            $response = $this->client->search($params);
            if (! empty($response['aggregations'])) {
                $data = $this->_sanitizeDistinctResponse($response['aggregations'], $columns, $includeDocCount);
            }
            //process limit and skip from all results
            if ($skip || $limit) {
                $data = array_slice($data, $skip, $limit);
            }
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_return($data, $response, $params, $this->_queryTag(__FUNCTION__));
    }

    //----------------------------------------------------------------------
    // Write Queries
    //----------------------------------------------------------------------

    /**
     * @throws QueryException
     */
    public function processSave($data, ArrayStore $options): Results
    {
        $id = null;
        if (isset($data['id'])) {
            $id = $data['id'];
            unset($data['id']);
        }
        if (isset($data['_index'])) {
            unset($data['_index']);
        }
        if (isset($data['_meta'])) {
            unset($data['_meta']);
        }

        $params = [
            'index' => $this->index,
            'body' => $data,
            'refresh' => $waitForRefresh->get(),
        ];
        if ($id) {
            $params['id'] = $id;
        }

        $response = [];
        $savedData = [];
        try {
            $response = $this->client->index($params);
            $savedData = ['id' => $response['_id']] + $data;
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_return($savedData, $response, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * Allows us to use the Bulk API.
     * Such speed!
     *
     * More Info:
     * - https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/indexing_documents.html#_bulk_indexing
     * - https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-bulk.html
     *
     * @throws QueryException
     */
    public function processInsertBulk(array $records, ArrayStore $options): array
    {
        $params = [
            'body' => [],
            // If we don't want to wait for elastic to refresh this needs to be set.
            'refresh' => $waitForRefresh->get(),
        ];

        // Create action/metadata pairs
        foreach ($records as $data) {
            $recordHeader['_index'] = $this->index;

            // We should ALWAYS have an ID however there are
            // some scenarios where we don't like when inserting records in to Pivot tables.
            //
            // we need to set that ID to be thew records _id
            if (isset($data['id'])) {
                $recordHeader['_id'] = $data['id'];
            }
            unset($data['id']);

            if (isset($data['_index'])) {
                unset($data['_index']);
            }
            if (isset($data['_meta'])) {
                unset($data['_meta']);
            }

            $params['body'][] = [
                'index' => $recordHeader,
            ];
            $params['body'][] = $data;
        }

        $finalResponse = [
            'hasErrors' => false,
            'total' => 0,
            'took' => 0,
            'success' => 0,
            'created' => 0,
            'modified' => 0,
            'failed' => 0,
            'data' => [],
            'error_bag' => [],
        ];
        try {
            $response = $this->client->bulk($params);
            $finalResponse['hasErrors'] = $response['errors'];
            $finalResponse['took'] = $response['took'];
            foreach ($response['items'] as $count => $hit) {
                $finalResponse['total']++;
                $payload = $params['body'][($count * 2) + 1];
                $id = $hit['index']['_id'];
                $record = ['id' => $id] + $payload;
                if (! empty($hit['index']['error'])) {
                    $finalResponse['failed']++;
                    $finalResponse['error_bag'][] = [
                        'error' => $hit['index']['error'],
                        'payload' => $record,
                    ];
                } else {
                    $finalResponse['success']++;
                    $finalResponse['success']++;
                    if ($hit['index']['result'] === 'created') {
                        $finalResponse['created']++;
                    } else {
                        $finalResponse['modified']++;
                    }
                    if ($returnData) {
                        $finalResponse['data'][] = $record;
                    }
                }
            }
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $finalResponse;
    }

    /**
     * @throws QueryException
     */
    public function processInsertOne($values, ArrayStore $options): Results
    {
        return $this->processSave($values, $waitForRefresh);
    }

    /**
     * @throws QueryException
     * @throws ParameterException
     */
    public function processUpdateByQuery($wheres, $newValues, $options, WaitFor $waitForRefresh = WaitFor::WAITFOR): Results
    {
      $params = $this->buildParams($this->index, $wheres, $options, []);

      $params['body'] = [...$params['body'], ...$newValues];
      $params['refresh'] = $waitForRefresh->getOperation();

      $process = [];
      try {
        $process = $this->client->updateByQuery($params);
      } catch (Exception $e) {
        $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
      }

      return $this->_return([], $process, $params, $this->_queryTag(__FUNCTION__));

    }

    /**
     * @throws QueryException
     * @throws ParameterException
     */
    public function processIncrementMany($wheres, $newValues, $options, bool $waitForRefresh = true): Results
    {
        //TODO INC on nested objects - maybe

        $incField = '';
        foreach ($newValues['inc'] as $field => $incValue) {
            $incField = $field;
        }
        $resultMeta['modified'] = 0;
        $resultMeta['failed'] = 0;
        $resultData = [];
        $data = $this->processFind($wheres, $options, []);
        if (! empty($data->data)) {
            foreach ($data->data as $currentData) {

                $currentValue = $currentData[$incField] ?? 0;
                $currentValue += $newValues['inc'][$incField];
                $currentData[$incField] = (int) $currentValue;

                if (! empty($newValues['set'])) {
                    foreach ($newValues['set'] as $field => $value) {
                        $currentData[$field] = $value;
                    }
                }
                $updated = $this->processSave($currentData, $waitForRefresh);
                if ($updated->isSuccessful()) {
                    $resultMeta['modified']++;
                    $resultData[] = $updated->data;
                } else {
                    $resultMeta['failed']++;
                }
            }
        }
        $params['query'] = $this->_buildQuery($wheres);
        $params['queryOptions'] = $options;
        $params['updateValues'] = $newValues;

        return $this->_return($resultData, $resultMeta, $params, $this->_queryTag(__FUNCTION__));
    }

    //----------------------------------------------------------------------
    // Delete Queries
    //----------------------------------------------------------------------

    /**
     * @throws QueryException
     * @throws ParameterException
     */
    public function processDeleteAll($wheres, $options = [], WaitFor $waitForRefresh = WaitFor::WAITFOR): Results
    {

        if (isset($wheres['_id'])) {
            $params = [
                'index' => $this->index,
                'id' => $wheres['_id'],
                'refresh' => $waitForRefresh->get(),
            ];
            try {
                $responseObject = $this->client->delete($params);
                $response = $responseObject->asArray();
                $response['deleteCount'] = $response['result'] === 'deleted' ? 1 : 0;

                return $this->_return($response['deleteCount'], $response, $params, $this->_queryTag(__FUNCTION__));
            } catch (Exception $e) {
                $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
            }
        }
        $response = [];
        $params = $this->buildParams($this->index, $wheres, $options);
        $params['refresh'] = $waitForRefresh->getOperation();
        try {
            $responseObject = $this->client->deleteByQuery($params);
            $response = $responseObject->asArray();
            $response['deleteCount'] = $response['deleted'] ?? 0;
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_return($response['deleteCount'], $response, $params, $this->_queryTag(__FUNCTION__));
    }




    private function _sanitizeAggsResponse($response, $params, $queryTag): Results
    {
        $meta['timed_out'] = $response['timed_out'];
        $meta['total'] = $response['hits']['total']['value'] ?? 0;
        $meta['max_score'] = $response['hits']['max_score'] ?? 0;
        $meta['sorts'] = [];

        $aggs = $response['aggregations'];
        $data = (count($aggs) === 1) ? reset($aggs)['value'] ?? 0 : array_map(fn ($value
        ) => $value['value'] ?? 0, $aggs);

        return $this->_return($data, $meta, $params, $queryTag);
    }

    //======================================================================
    // Distinct Aggregates
    //======================================================================

    public function processDistinctAggregate($function, $wheres, $options, $columns): Results
    {
        return $this->{'_'.$function.'DistinctAggregate'}($wheres, $options, $columns);
    }

    /**
     * @throws ParameterException
     * @throws QueryException
     */
    private function _countDistinctAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres);
        $count = 0;
        $meta = [];
        try {
            $process = $this->processDistinct($wheres, $options, $columns);
            $count = count($process->data);
            $meta = $process->getMetaData();
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_return($count, $meta, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * @throws ParameterException
     * @throws QueryException
     */
    private function _maxDistinctAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres);
        $max = 0;
        $meta = [];
        try {
            $process = $this->processDistinct($wheres, $options, $columns);

            if (! empty($process->data)) {
                foreach ($process->data as $datum) {
                    if (! empty($datum[$columns[0]]) && is_numeric($datum[$columns[0]])) {
                        $max = max($max, $datum[$columns[0]]);
                    }
                }
            }
            $meta = $process->getMetaData();
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_return($max, $meta, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * @throws ParameterException
     * @throws QueryException
     */
    private function _minDistinctAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres);
        $min = 0;
        $meta = [];
        try {
            $process = $this->processDistinct($wheres, $options, $columns);
            $hasBeenSet = false;
            if (! empty($process->data)) {
                foreach ($process->data as $datum) {
                    if (! empty($datum[$columns[0]]) && is_numeric($datum[$columns[0]])) {
                        if (! $hasBeenSet) {
                            $min = $datum[$columns[0]];
                            $hasBeenSet = true;
                        } else {
                            $min = min($min, $datum[$columns[0]]);
                        }
                    }
                }
            }
            $meta = $process->getMetaData();
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_return($min, $meta, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * @throws ParameterException
     * @throws QueryException
     */
    private function _sumDistinctAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres);
        $sum = 0;
        $meta = [];
        try {
            $process = $this->processDistinct($wheres, $options, $columns);
            if (! empty($process->data)) {
                foreach ($process->data as $datum) {
                    if (! empty($datum[$columns[0]]) && is_numeric($datum[$columns[0]])) {
                        $sum += $datum[$columns[0]];
                    }
                }
            }
            $meta = $process->getMetaData();
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_return($sum, $meta, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * @throws ParameterException
     * @throws QueryException
     */
    private function _avgDistinctAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres);
        $sum = 0;
        $count = 0;
        $avg = 0;
        $meta = [];
        try {
            $process = $this->processDistinct($wheres, $options, $columns);

            if (! empty($process->data)) {
                foreach ($process->data as $datum) {
                    if (! empty($datum[$columns[0]]) && is_numeric($datum[$columns[0]])) {
                        $count++;
                        $sum += $datum[$columns[0]];
                    }
                }
            }
            if ($count > 0) {
                $avg = $sum / $count;
            }
            $meta = $process->getMetaData();
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_return($avg, $meta, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * @throws QueryException
     */
    private function _matrixDistinctAggregate($wheres, $options, $columns)
    {
        $this->_throwError(new Exception('Matrix distinct aggregate not supported', 500), [], $this->_queryTag(__FUNCTION__));
    }

    //======================================================================
    // Helpers
    //======================================================================



    //======================================================================
    // Private & Sanitization methods
    //======================================================================

    private function _return($data, $meta, $params, $queryTag): Results
    {
        if (is_object($meta)) {
            $metaAsArray = [];
            if (method_exists($meta, 'asArray')) {
                $metaAsArray = $meta->asArray();
            }
            $results = new Results($data, $metaAsArray, $params, $queryTag);
        } else {
            $results = new Results($data, $meta, $params, $queryTag);
        }

        return $results;
    }

    private function _queryTag($function): string
    {
        return str_replace('process', '', $function);
    }



    private function _sanitizePitSearchResponse($response, $params, $queryTag)
    {

        $meta['timed_out'] = $response['timed_out'];
        $meta['total'] = $response['hits']['total']['value'] ?? 0;
        $meta['max_score'] = $response['hits']['max_score'] ?? 0;
        $meta['sort'] = null;
        $data = [];
        if (! empty($response['hits']['hits'])) {
            foreach ($response['hits']['hits'] as $hit) {
                $datum = [];
                $datum['_index'] = $hit['_index'];
                $datum['id'] = $hit['_id'];
                if (! empty($hit['_source'])) {
                    foreach ($hit['_source'] as $key => $value) {
                        $datum[$key] = $value;
                    }
                }
                if (! empty($hit['sort'][0])) {
                    $meta['sort'] = $hit['sort'];
                }
                $data[] = $datum;
            }
        }

        return $this->_return($data, $meta, $params, $queryTag);
    }






    private function _parseFieldMap(array $mapping): array
    {
        $fields = [];
        $mapping = reset($mapping);
        if (! empty($mapping['mappings'])) {
            foreach ($mapping['mappings'] as $key => $item) {
                // Check if 'mapping' key exists and is not empty
                if (! empty($item['mapping'])) {
                    foreach ($item['mapping'] as $details) {
                        if (isset($details['type'])) {
                            $fields[$key] = $details['type'];
                        }
                        // Check if nested fields exist within the field's details
                        if (isset($details['fields'])) {
                            foreach ($details['fields'] as $subField => $subDetails) {
                                $subFieldName = $key.'.'.$subField;
                                $fields[$subFieldName] = $subDetails['type'];
                            }
                        }
                    }
                }
            }
        }
        $mappings = Collection::make($fields);
        $mappings = $mappings->sortKeys();

        return $mappings->toArray();
    }

    //======================================================================
    // Error and logging
    //======================================================================

    /**
     * @throws QueryException
     */
    private function _throwError(Exception $exception, $params, $queryTag): QueryException
    {
        $previous = get_class($exception);
        $errorMsg = $exception->getMessage();
        $errorCode = $exception->getCode();
        $queryTag = str_replace('_', '', $queryTag);
        $error = new Results([], [], $params, $queryTag);
        $error->setError($errorMsg, $errorCode);

        $meta = $error->getMetaDataAsArray();
        $details = [
            'error' => $meta['error']['msg'],
            'details' => $meta['error']['data'],
            'code' => $errorCode,
            'exception' => $previous,
            'query' => $queryTag,
            'params' => $params,
            'original' => $errorMsg,
        ];
        if ($this->errorLogger) {
            $this->_logQuery($error, $details);
        }
        // For details catch $exception then $exception->getDetails()
        throw new QueryException($meta['error']['msg'], $errorCode, new $previous, $details);
    }

    private function _logQuery(Results $results, $details)
    {
        $body = $results->getLogFormattedMetaData();
        if ($details) {
            $body['details'] = (array) $details;
        }
        $params = [
            'index' => $this->errorLogger,
            'body' => $body,
        ];
        try {
            $this->client->index($params);
        } catch (Exception $e) {
            //ignore if problem writing query log
        }
    }

    //----------------------------------------------------------------------
    // Meta Stasher
    //----------------------------------------------------------------------



    //    private function _parseSort($sort, $sortParams): array
    //    {
    //        $sortValues = [];
    //        foreach ($sort as $key => $value) {
    //            $sortValues[array_key_first($sortParams[$key])] = $value;
    //        }
    //
    //        return $sortValues;
    //    }
}

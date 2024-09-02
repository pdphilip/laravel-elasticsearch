<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\DSL;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Exception;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\DSL\exceptions\ParameterException;
use PDPhilip\Elasticsearch\DSL\exceptions\QueryException;

class Bridge
{
    use IndexInterpreter, QueryBuilder;

    protected Connection $connection;

    protected Client $client;

    protected ?string $errorLogger;

    protected ?int $maxSize = 10; //ES default

    private ?string $index;

    private ?array $stashedMeta;

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
    public function processPitFind(
        $wheres,
        $options,
        $columns,
        $pitId,
        $searchAfter = false,
        $keepAlive = '5m'
    ): Results {
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
    public function processFind($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres, $options, $columns);

        return $this->_returnSearch($params, __FUNCTION__);
    }

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
    public function processSave($data, $refresh): Results
    {
        $id = null;
        if (isset($data['_id'])) {
            $id = $data['_id'];
            unset($data['_id']);
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
        ];
        if ($id) {
            $params['id'] = $id;
        }
        if ($refresh) {
            $params['refresh'] = $refresh;
        }
        $response = [];
        $savedData = [];
        try {
            $response = $this->client->index($params);
            $savedData = ['_id' => $response['_id']] + $data;
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
    public function processInsertBulk(array $records, $returnData): array
    {
        $params = ['body' => []];

        // Create action/metadata pairs
        foreach ($records as $data) {
            $recordHeader['_index'] = $this->index;

            if (isset($data['_id'])) {
                $recordHeader['_id'] = $data['_id'];
                unset($data['_id']);
            }
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

                if (! empty($hit['index']['error'])) {
                    $finalResponse['failed']++;
                    $finalResponse['error_bag'][] = [
                        'error' => $hit['index']['error'],
                        'payload' => $payload,
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
                        $id = $hit['index']['_id'];
                        $record = ['_id' => $id] + $payload;
                        $finalResponse['data'][] = $record;
                    }
                }
            }
            ////iterate over the return and return an array of Results
            //foreach ($response['items'] as $count => $hit) {
            //
            //    // We use $params['body'] here again to get the body
            //    // The index we want is always +1 above our insert index
            //    $savedData = ['_id' => $hit['index']['_id']] + $params['body'][($count * 2) + 1];
            //    $finalResponse[] = $this->_return($savedData, $hit['index'], $params, $this->_queryTag(__FUNCTION__));
            //}
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $finalResponse;
    }

    /**
     * @throws QueryException
     */
    public function processInsertOne($values, $refresh): Results
    {
        return $this->processSave($values, $refresh);
    }

    /**
     * @throws QueryException
     * @throws ParameterException
     */
    public function processUpdateMany($wheres, $newValues, $options, $refresh = null): Results
    {
        $resultMeta['modified'] = 0;
        $resultMeta['failed'] = 0;
        $resultData = [];
        $data = $this->processFind($wheres, $options, []);

        if (! empty($data->data)) {
            foreach ($data->data as $currentData) {

                foreach ($newValues as $field => $value) {
                    $currentData[$field] = $value;
                }
                $updated = $this->processSave($currentData, $refresh);
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

    /**
     * @throws QueryException
     * @throws ParameterException
     */
    public function processIncrementMany($wheres, $newValues, $options, $refresh): Results
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
                $updated = $this->processSave($currentData, $refresh);
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
    public function processDeleteAll($wheres, $options = []): Results
    {
        if (isset($wheres['_id'])) {
            $params = [
                'index' => $this->index,
                'id' => $wheres['_id'],
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
        try {
            $responseObject = $this->client->deleteByQuery($params);
            $response = $responseObject->asArray();
            $response['deleteCount'] = $response['deleted'] ?? 0;
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_return($response['deleteCount'], $response, $params, $this->_queryTag(__FUNCTION__));
    }

    //----------------------------------------------------------------------
    // Index administration
    //----------------------------------------------------------------------

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function processGetIndices($all): array
    {
        $index = $this->index;
        if ($all) {
            $index = '*';
        }
        $response = $this->client->indices()->get(['index' => $index]);

        return $response->asArray();
    }

    public function processIndexExists($index): bool
    {
        $params = ['index' => $index];
        try {
            $test = $this->client->indices()->exists($params);
        } catch (Exception $e) {
            return false;
        }

        return $test->getStatusCode() == 200;
    }

    /**
     * @throws QueryException
     */
    public function processIndexSettings($index): array
    {
        $params = ['index' => $index];
        $response = [];
        try {
            $response = $this->client->indices()->getSettings($params);
            $result = $this->_return($response, $response, $params, $this->_queryTag(__FUNCTION__));
            $response = $result->data->asArray();
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $response;
    }

    /**
     * @throws QueryException
     */
    public function processIndexCreate($settings): bool
    {
        $params = $this->buildIndexMap($this->index, $settings);
        $created = false;
        try {
            $response = $this->client->indices()->create($params);
            $created = $response->asArray();
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return ! empty($created);
    }

    /**
     * @throws QueryException
     */
    public function processIndexDelete(): bool
    {
        $params = ['index' => $this->index];
        try {
            $this->client->indices()->delete($params);
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return true;
    }

    /**
     * @throws QueryException
     */
    public function processIndexModify($settings): bool
    {

        $params = $this->buildIndexMap($this->index, $settings);
        $params['body']['_source']['enabled'] = true;
        $props = $params['body']['mappings']['properties'];
        unset($params['body']['mappings']);
        $params['body']['properties'] = $props;

        try {
            $response = $this->client->indices()->putMapping($params);
            $result = $this->_return(true, $response, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return true;
    }

    /**
     * @throws QueryException
     */
    public function processReIndex($oldIndex, $newIndex): Results
    {
        $prefix = $this->indexPrefix;
        if ($prefix) {
            $oldIndex = $prefix.'_'.$oldIndex;
            $newIndex = $prefix.'_'.$newIndex;
        }
        $params['body']['source']['index'] = $oldIndex;
        $params['body']['dest']['index'] = $newIndex;
        $resultData = [];
        $result = [];
        try {
            $response = $this->client->reindex($params);
            $result = $response->asArray();
            $resultData = [
                'took' => $result['took'],
                'total' => $result['total'],
                'created' => $result['created'],
                'updated' => $result['updated'],
                'deleted' => $result['deleted'],
                'batches' => $result['batches'],
                'version_conflicts' => $result['version_conflicts'],
                'noops' => $result['noops'],
                'retries' => $result['retries'],
            ];
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_return($resultData, $result, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * @throws QueryException
     */
    public function processIndexAnalyzerSettings($settings): bool
    {
        $params = $this->buildAnalyzerSettings($this->index, $settings);
        try {
            $this->client->indices()->close(['index' => $this->index]);
            $response = $this->client->indices()->putSettings($params);
            $result = $this->_return(true, $response, $params, $this->_queryTag(__FUNCTION__));
            $this->client->indices()->open(['index' => $this->index]);
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return true;
    }

    //----------------------------------------------------------------------
    // Aggregates
    //----------------------------------------------------------------------
    /**
     * @throws QueryException
     * @throws ParameterException
     */
    public function processMultipleAggregate($functions, $wheres, $options, $column): Results
    {
        $params = $this->buildParams($this->index, $wheres, $options);
        $process = [];
        try {
            $params['body']['aggs'] = ParameterBuilder::multipleAggregations($functions, $column);
            $process = $this->client->search($params);
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_return($process['aggregations'] ?? [], $process, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     *  Aggregate entry point
     */
    public function processAggregate($function, $wheres, $options, $columns): Results
    {
        return $this->{'_'.$function.'Aggregate'}($wheres, $options, $columns);
    }

    /**
     * @throws QueryException
     * @throws ParameterException
     */
    public function _countAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres);
        $process = [];
        try {
            $process = $this->client->count($params);
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_return($process['count'] ?? 0, $process, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * @throws ParameterException
     * @throws QueryException
     */
    private function _maxAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres, $options);
        if (is_array($columns[0])) {
            $columns = $columns[0];
        }
        $process = [];
        try {
            foreach ($columns as $column) {
                $params['body']['aggs']['max_'.$column] = ParameterBuilder::maxAggregation($column);
            }
            $process = $this->client->search($params);
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_sanitizeAggsResponse($process, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * @throws QueryException
     * @throws ParameterException
     */
    private function _minAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres, $options);
        if (is_array($columns[0])) {
            $columns = $columns[0];
        }
        $process = [];
        try {
            foreach ($columns as $column) {
                $params['body']['aggs']['min_'.$column] = ParameterBuilder::minAggregation($column);
            }
            $process = $this->client->search($params);
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_sanitizeAggsResponse($process, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * @throws QueryException
     * @throws ParameterException
     */
    private function _sumAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres, $options);
        if (is_array($columns[0])) {
            $columns = $columns[0];
        }
        $process = [];
        try {
            foreach ($columns as $column) {
                $params['body']['aggs']['sum_'.$column] = ParameterBuilder::sumAggregation($column);
            }
            $process = $this->client->search($params);
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_sanitizeAggsResponse($process, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * @throws ParameterException
     * @throws QueryException
     */
    private function _avgAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres, $options);
        if (is_array($columns[0])) {
            $columns = $columns[0];
        }
        $process = [];
        try {
            foreach ($columns as $column) {
                $params['body']['aggs']['avg_'.$column] = ParameterBuilder::avgAggregation($column);
            }
            $process = $this->client->search($params);
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_sanitizeAggsResponse($process, $params, $this->_queryTag(__FUNCTION__));
    }

    /**
     * @throws QueryException
     * @throws ParameterException
     */
    private function _matrixAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres, $options);
        $process = [];
        try {
            $params['body']['aggs']['statistics'] = ParameterBuilder::matrixAggregation($columns);
            $process = $this->client->search($params);
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $this->_return($process['aggregations']['statistics'] ?? [], $process, $params, $this->_queryTag(__FUNCTION__));
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

    /**
     * @throws QueryException
     */
    public function parseRequiredKeywordMapping($field): ?string
    {
        $mappings = $this->processIndexMappings($this->index);
        $map = reset($mappings);
        if (! empty($map['mappings']['properties'][$field])) {
            $fieldMap = $map['mappings']['properties'][$field];
            if (! empty($fieldMap['type']) && $fieldMap['type'] === 'keyword') {
                //primary Map is field. Use as is
                return $field;
            }
            if (! empty($fieldMap['fields']['keyword'])) {
                return $field.'.keyword';
            }
        }

        return null;
    }

    /**
     * @throws QueryException
     */
    public function processIndexMappings($index): array
    {
        $params = ['index' => $index];
        $result = [];
        try {
            $responseObject = $this->client->indices()->getMapping($params);
            $response = $responseObject->asArray();
            $result = $this->_return($response, $response, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
        }

        return $result->data;
    }

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
                $datum['_id'] = $hit['_id'];
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

    private function _sanitizeSearchResponse($response, $params, $queryTag)
    {
        $meta['took'] = $response['took'] ?? 0;
        $meta['timed_out'] = $response['timed_out'];
        $meta['total'] = $response['hits']['total']['value'] ?? 0;
        $meta['max_score'] = $response['hits']['max_score'] ?? 0;
        $meta['shards'] = $response['_shards'] ?? [];
        $data = [];
        if (! empty($response['hits']['hits'])) {
            foreach ($response['hits']['hits'] as $hit) {
                $datum = [];
                $datum['_index'] = $hit['_index'];
                $datum['_id'] = $hit['_id'];
                if (! empty($hit['_source'])) {

                    foreach ($hit['_source'] as $key => $value) {
                        $datum[$key] = $value;
                    }
                }
                if (! empty($hit['inner_hits'])) {
                    foreach ($hit['inner_hits'] as $innerKey => $innerHit) {
                        $datum[$innerKey] = $this->_filterInnerHits($innerHit);
                    }
                }

                //Meta data
                if (! empty($hit['highlight'])) {
                    $datum['_meta']['highlights'] = $this->_sanitizeHighlights($hit['highlight']);
                }

                $datum['_meta']['_index'] = $hit['_index'];
                $datum['_meta']['_id'] = $hit['_id'];
                if (! empty($hit['_score'])) {
                    $datum['_meta']['_score'] = $hit['_score'];
                }
                $datum['_meta']['_query'] = $meta;
                // If we are sorting we need to store it to be able to pass it on in the search after.
                if (! empty($hit['sort'])) {
                    $datum['_meta']['sort'] = $hit['sort'];
                }
                $datum['_meta'] = $this->_attachStashedMeta($datum['_meta']);
                $data[] = $datum;
            }
        }

        return $this->_return($data, $meta, $params, $queryTag);
    }

    private function _sanitizeDistinctResponse($response, $columns, $includeDocCount): array
    {
        $keys = [];
        foreach ($columns as $column) {
            $keys[] = 'by_'.$column;
        }

        return $this->_processBuckets($columns, $keys, $response, 0, $includeDocCount);
    }

    private function _processBuckets($columns, $keys, $response, $index, $includeDocCount, $currentData = []): array
    {
        $data = [];
        if (! empty($response[$keys[$index]]['buckets'])) {
            foreach ($response[$keys[$index]]['buckets'] as $res) {

                $datum = $currentData;

                $col = $columns[$index];
                if (str_contains($col, '.keyword')) {
                    $col = str_replace('.keyword', '', $col);
                }

                $datum[$col] = $res['key'];

                if ($includeDocCount) {
                    $datum[$col.'_count'] = $res['doc_count'];
                }

                if (isset($columns[$index + 1])) {
                    $nestedData = $this->_processBuckets($columns, $keys, $res, $index + 1, $includeDocCount, $datum);

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

    private function _sanitizeRawAggsResponse($response, $params, $queryTag)
    {
        $meta['timed_out'] = $response['timed_out'];
        $meta['total'] = $response['hits']['total']['value'] ?? 0;
        $meta['max_score'] = $response['hits']['max_score'] ?? 0;
        $meta['sorts'] = [];
        $data = [];
        if (! empty($response['aggregations'])) {
            foreach ($response['aggregations'] as $key => $values) {
                $data[$key] = $this->_formatAggs($key, $values)[$key];
            }
        }

        return $this->_return($data, $meta, $params, $queryTag);
    }

    private function _sanitizeHighlights($highlights)
    {
        //remove keyword results
        foreach ($highlights as $field => $vals) {
            if (str_contains($field, '.keyword')) {
                $cleanField = str_replace('.keyword', '', $field);
                if (isset($highlights[$cleanField])) {
                    unset($highlights[$field]);
                } else {
                    $highlights[$cleanField] = $vals;
                }
            }
        }

        return $highlights;
    }

    private function _filterInnerHits($innerHit)
    {
        $hits = [];
        foreach ($innerHit['hits']['hits'] as $inner) {
            $innerDatum = [];
            if (! empty($inner['_source'])) {
                foreach ($inner['_source'] as $innerSourceKey => $innerSourceValue) {
                    $innerDatum[$innerSourceKey] = $innerSourceValue;
                }
            }
            $hits[] = $innerDatum;
        }

        return $hits;
    }

    private function _formatAggs($key, $values)
    {
        $data[$key] = [];
        $aggTypes = ['buckets', 'values'];

        foreach ($values as $subKey => $value) {
            if (in_array($subKey, $aggTypes)) {
                $data[$key] = $this->_formatAggs($subKey, $value)[$subKey];
            } elseif (is_array($value)) {
                $data[$key][$subKey] = $this->_formatAggs($subKey, $value)[$subKey];
            } else {
                $data[$key][$subKey] = $value;
            }
        }

        return $data;
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
        $this->connection->rebuildConnection();
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

    private function _stashMeta($meta): void
    {
        $this->stashedMeta = $meta;
    }

    private function _attachStashedMeta($meta): mixed
    {
        if (! empty($this->stashedMeta)) {
            $meta = array_merge($meta, $this->stashedMeta);
        }

        return $meta;
    }

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

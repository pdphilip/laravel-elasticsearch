<?php

namespace PDPhilip\Elasticsearch\DSL;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Exception;
use Elastic\Elasticsearch\Client;


class Bridge
{
    
    use QueryBuilder, IndexInterpreter;
    
    protected Client $client;
    
    protected mixed $queryLogger = false;
    
    protected mixed $queryLoggerOnErrorOnly = true;
    
    protected int|null $maxSize = 10; //ES default
    
    private string|null $index;
    
    
    public function __construct(Client $client, $index, $maxSize)
    {
        $this->client = $client;
        $this->index = $index;
        $this->maxSize = $maxSize;
        
        if (!empty(config('database.connections.elasticsearch.logging.index'))) {
            $this->queryLogger = config('database.connections.elasticsearch.logging.index');
            $this->queryLoggerOnErrorOnly = config('database.connections.elasticsearch.logging.errorOnly');
        }
        
    }
    
    
    //----------------------------------------------------------------------
    //  BYO Query
    //----------------------------------------------------------------------
    
    /**
     * @throws Exception
     */
    public function processSearchRaw($bodyParams): Results
    {
        $params = [
            'index' => $this->index,
            'body'  => $bodyParams,
        
        ];
        try {
            $process = $this->client->search($params);
            
            return $this->_sanitizeSearchResponse($process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            
            $error = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($error->errorMessage);
        }
    }
    
    /**
     * @throws Exception
     */
    public function processIndicesDsl($method, $params): Results
    {
        try {
            $process = $this->client->indices()->{$method}($params);
            
            return $this->_sanitizeSearchResponse($process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            
            $error = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($error->errorMessage);
        }
    }
    
    
    //----------------------------------------------------------------------
    // Read Queries
    //----------------------------------------------------------------------
    
    /**
     * @throws Exception
     */
    public function processFind($wheres, $options, $columns): Results
    {
        
        $params = $this->buildParams($this->index, $wheres, $options, $columns);
        
        return $this->_returnSearch($params, __FUNCTION__);
    }
    
    /**
     * @throws Exception
     */
    public function processSearch($searchParams, $searchOptions, $wheres, $opts, $fields, $cols)
    {
        
        $params = $this->buildSearchParams($this->index, $searchParams, $searchOptions, $wheres, $opts, $fields, $cols);
        
        return $this->_returnSearch($params, __FUNCTION__);
        
    }
    
    protected function _returnSearch($params, $source)
    {
        if (empty($params['size'])) {
            $params['size'] = $this->maxSize;
        }
        try {
            $process = $this->client->search($params);
            
            return $this->_sanitizeSearchResponse($process, $params, $this->_queryTag($source));
            
        } catch (Exception $e) {
            
            $result = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
    }
    
    
    public function processDistinct($wheres, $options, $columns, $includeDocCount = false): Results
    {
        try {
            if ($columns && !is_array($columns)) {
                $columns = [$columns];
            }
            $sort = $options['sort'] ?? [];
            $skip = $options['skip'] ?? false;
            $limit = $options['limit'] ?? false;
            unset($options['sort']);
            unset($options['skip']);
            unset($options['limit']);
            
            $params = $this->buildParams($this->index, $wheres, $options);
            $params['body']['aggs'] = $this->createNestedAggs($columns, $sort);
            
            $response = $this->client->search($params);
            
            
            $data = [];
            if (!empty($response['aggregations'])) {
                $data = $this->_sanitizeDistinctResponse($response['aggregations'], $columns, $includeDocCount);
            }
            
            //process limit and skip from all results
            if ($skip || $limit) {
                $data = array_slice($data, $skip, $limit);
            }
            
            return $this->_return($data, $response, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            
            $result = $this->_returnError($e->getMessage(), $e->getCode(), [], $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
        
    }
    
    /**
     * @throws Exception
     */
    public function processShowQuery($wheres, $options, $columns)
    {
        $params = $this->buildParams($this->index, $wheres, $options, $columns);
        
        return $params['body']['query']['query_string']['query'] ?? null;
    }
    
    
    //----------------------------------------------------------------------
    // Write Queries
    //----------------------------------------------------------------------
    
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
        $params = [
            'index' => $this->index,
            'body'  => $data,
        ];
        if ($id) {
            $params['id'] = $id;
            
        }
        if ($refresh) {
            $params['refresh'] = $refresh;
        }
        
        try {
            $response = $this->client->index($params);
            $savedData = ['_id' => $response['_id']] + $data;
            
            return $this->_return($savedData, $response, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
        
        
    }
    
    public function processInsertOne($values, $refresh): Results
    {
        return $this->processSave($values, $refresh);
    }
    
    /**
     * @throws Exception
     */
    public function processUpdateMany($wheres, $newValues, $options, $refresh = null): Results
    {
        $resultMeta['modified'] = 0;
        $resultMeta['failed'] = 0;
        $resultData = [];
        $data = $this->processFind($wheres, $options, []);
        
        if (!empty($data->data)) {
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
     * @throws Exception
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
        if (!empty($data->data)) {
            foreach ($data->data as $currentData) {
                
                $currentValue = $currentData[$incField] ?? 0;
                $currentValue += $newValues['inc'][$incField];
                $currentData[$incField] = (int)$currentValue;
                
                if (!empty($newValues['set'])) {
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
    
    public function processDeleteAll($wheres, $options = []): Results
    {
        if (isset($wheres['_id'])) {
            $params = [
                'index' => $this->index,
                'id'    => $wheres['_id'],
            ];
            try {
                $responseObject = $this->client->delete($params);
                $response = $responseObject->asArray();
                $response['deleteCount'] = $response['result'] === 'deleted' ? 1 : 0;
                
                return $this->_return($response['deleteCount'], $response, $params, $this->_queryTag(__FUNCTION__));
            } catch (Exception $e) {
                return $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            }
        }
        try {
            $params = $this->buildParams($this->index, $wheres, $options);
            $responseObject = $this->client->deleteByQuery($params);
            $response = $responseObject->asArray();
            $response['deleteCount'] = $response['deleted'] ?? 0;
            
            return $this->_return($response['deleteCount'], $response, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            $error = $this->_returnError($e->getMessage(), $e->getCode(), [], $this->_queryTag(__FUNCTION__));
            throw new Exception($error->errorMessage);
        }
        
    }
    
    public function processScript($id, $script)
    {
//        $params = [
//            'id'    => $id,
//            'index' => $this->index,
//        ];
//        if ($script) {
//            $params['body']['script']['source'] = $script;
//        }
//
//        $response = $this->client->update($params);
//
//        $n = new self($this->index);
//        $find = $n->processFind($id);

//        return $this->_return($find->data, $response, $params, $this->_queryTag(__FUNCTION__));
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
            
            return $test->getStatusCode() == 200;
        } catch (Exception $e) {
            return false;
        }
        
    }
    
    /**
     * @throws Exception
     */
    public function processIndexMappings($index): mixed
    {
        $params = ['index' => $index];
        try {
            $responseObject = $this->client->indices()->getMapping($params);
            $response = $responseObject->asArray();
            $result = $this->_return($response, $response, $params, $this->_queryTag(__FUNCTION__));
            
            return $result->data;
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
    }
    
    /**
     * @throws Exception
     */
    public function processIndexSettings($index): mixed
    {
        $params = ['index' => $index];
        try {
            $response = $this->client->indices()->getSettings($params);
            $result = $this->_return($response, $response, $params, $this->_queryTag(__FUNCTION__));
            
            return $result->data->asArray();
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
    }
    
    /**
     * @throws Exception
     */
    public function processIndexCreate($settings)
    {
        $params = $this->buildIndexMap($this->index, $settings);
        try {
            $response = $this->client->indices()->create($params);
            
            $result = $this->_return(true, $response, $params, $this->_queryTag(__FUNCTION__));
            
            return true;
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), [], $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
        
    }
    
    /**
     * @throws Exception
     */
    public function processIndexDelete(): bool
    {
        $params = ['index' => $this->index];
        try {
            $response = $this->client->indices()->delete($params);
            $this->_return(true, $response, $params, $this->_queryTag(__FUNCTION__));
            
            return true;
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
        
        
    }
    
    /**
     * @throws Exception
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
            
            return true;
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
        
    }
    
    /**
     * @throws Exception
     */
    public function processReIndex($oldIndex, $newIndex): Results
    {
        $prefix = str_replace('*', '', $this->index);
        if ($prefix) {
            $oldIndex = $prefix.'_'.$oldIndex;
            $newIndex = $prefix.'_'.$newIndex;
        }
        $params['body']['source']['index'] = $oldIndex;
        $params['body']['dest']['index'] = $newIndex;
        try {
            $response = $this->client->reindex($params);
            $result = $response->asArray();
            $resultData = [
                'took'              => $result['took'],
                'total'             => $result['total'],
                'created'           => $result['created'],
                'updated'           => $result['updated'],
                'deleted'           => $result['deleted'],
                'batches'           => $result['batches'],
                'version_conflicts' => $result['version_conflicts'],
                'noops'             => $result['noops'],
                'retries'           => $result['retries'],
            ];
            
            return $this->_return($resultData, $result, $params, $this->_queryTag(__FUNCTION__));
            
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
    }
    
    /**
     * @throws Exception
     */
    public function processIndexAnalyzerSettings($settings): bool
    {
        $params = $this->buildAnalyzerSettings($this->index, $settings);
        try {
            $this->client->indices()->close(['index' => $this->index]);
            $response = $this->client->indices()->putSettings($params);
            $result = $this->_return(true, $response, $params, $this->_queryTag(__FUNCTION__));
            $this->client->indices()->open(['index' => $this->index]);
            
            return true;
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
    }
    
    
    //----------------------------------------------------------------------
    // Aggregates
    //----------------------------------------------------------------------
    
    /**
     *  Aggregate entry point
     *
     * @param $function
     * @param $wheres
     * @param $options
     * @param $columns
     *
     * @return mixed
     */
    public function processAggregate($function, $wheres, $options, $columns): Results
    {
        return $this->{'_'.$function.'Aggregate'}($wheres, $options, $columns);
    }
    
    public function _countAggregate($wheres, $options, $columns): Results
    {
        try {
            $params = $this->buildParams($this->index, $wheres);
            $process = $this->client->count($params);
            
            return $this->_return($process['count'] ?? 0, $process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            
            $result = $this->_returnError($e->getMessage(), $e->getCode(), [], $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
        
    }
    
    private function _maxAggregate($wheres, $options, $columns): Results
    {
        try {
            $params = $this->buildParams($this->index, $wheres, $options);
            $params['body']['aggs']['max_value'] = ParameterBuilder::maxAggregation($columns[0]);
            $process = $this->client->search($params);
            
            return $this->_return($process['aggregations']['max_value']['value'] ?? 0, $process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            
            $result = $this->_returnError($e->getMessage(), $e->getCode(), [], $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
    }
    
    private function _minAggregate($wheres, $options, $columns): Results
    {
        try {
            $params = $this->buildParams($this->index, $wheres, $options);
            $params['body']['aggs']['min_value'] = ParameterBuilder::minAggregation($columns[0]);
            $process = $this->client->search($params);
            
            return $this->_return($process['aggregations']['min_value']['value'] ?? 0, $process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), [], $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
    }
    
    private function _sumAggregate($wheres, $options, $columns): Results
    {
        
        try {
            $params = $this->buildParams($this->index, $wheres, $options);
            $params['body']['aggs']['sum_value'] = ParameterBuilder::sumAggregation($columns[0]);
            $process = $this->client->search($params);
            
            return $this->_return($process['aggregations']['sum_value']['value'] ?? 0, $process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            
            $result = $this->_returnError($e->getMessage(), $e->getCode(), [], $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
        
    }
    
    private function _avgAggregate($wheres, $options, $columns): Results
    {
        try {
            $params = $this->buildParams($this->index, $wheres, $options);
            $params['body']['aggs']['avg_value'] = ParameterBuilder::avgAggregation($columns[0]);
            $process = $this->client->search($params);
            
            return $this->_return($process['aggregations']['avg_value']['value'] ?? 0, $process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), [], $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
    }
    
    private function _matrixAggregate($wheres, $options, $columns): Results
    {
        try {
            $params = $this->buildParams($this->index, $wheres, $options);
            $params['body']['aggs']['statistics'] = ParameterBuilder::matrixAggregation($columns);
            $process = $this->client->search($params);
            
            return $this->_return($process['aggregations']['statistics'] ?? [], $process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), [], $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
        
    }
    //----------------------------------------------------------------------
    // Distinct Aggregates
    //----------------------------------------------------------------------
    
    public function processDistinctAggregate($function, $wheres, $options, $columns): Results
    {
        return $this->{'_'.$function.'DistinctAggregate'}($wheres, $options, $columns);
    }
    
    private function _countDistinctAggregate($wheres, $options, $columns): Results
    {
        try {
            $params = $this->buildParams($this->index, $wheres);
            $process = $this->processDistinct($wheres, $options, $columns);
            $count = count($process->data);
            
            return $this->_return($count, $process->getMetaData(), $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            
            $result = $this->_returnError($e->getMessage(), $e->getCode(), [], $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
        
    }
    
    
    private function _minDistinctAggregate($wheres, $options, $columns): Results
    {
        try {
            $params = $this->buildParams($this->index, $wheres);
            $process = $this->processDistinct($wheres, $options, $columns);
            
            $min = 0;
            $hasBeenSet = false;
            if (!empty($process->data)) {
                foreach ($process->data as $datum) {
                    if (!empty($datum[$columns[0]]) && is_numeric($datum[$columns[0]])) {
                        if (!$hasBeenSet) {
                            $min = $datum[$columns[0]];
                            $hasBeenSet = true;
                        } else {
                            $min = min($min, $datum[$columns[0]]);
                        }
                        
                    }
                }
            }
            
            return $this->_return($min, $process->getMetaData(), $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            
            $result = $this->_returnError($e->getMessage(), $e->getCode(), [], $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
        
    }
    
    private function _maxDistinctAggregate($wheres, $options, $columns): Results
    {
        try {
            $params = $this->buildParams($this->index, $wheres);
            $process = $this->processDistinct($wheres, $options, $columns);
            
            $max = 0;
            if (!empty($process->data)) {
                foreach ($process->data as $datum) {
                    if (!empty($datum[$columns[0]]) && is_numeric($datum[$columns[0]])) {
                        $max = max($max, $datum[$columns[0]]);
                    }
                }
            }
            
            
            return $this->_return($max, $process->getMetaData(), $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            
            $result = $this->_returnError($e->getMessage(), $e->getCode(), [], $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
        
    }
    
    
    private function _sumDistinctAggregate($wheres, $options, $columns): Results
    {
        try {
            $params = $this->buildParams($this->index, $wheres);
            $process = $this->processDistinct($wheres, $options, $columns);
            $sum = 0;
            if (!empty($process->data)) {
                foreach ($process->data as $datum) {
                    if (!empty($datum[$columns[0]]) && is_numeric($datum[$columns[0]])) {
                        $sum += $datum[$columns[0]];
                    }
                }
            }
            
            return $this->_return($sum, $process->getMetaData(), $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            
            $result = $this->_returnError($e->getMessage(), $e->getCode(), [], $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
        
    }
    
    private function _avgDistinctAggregate($wheres, $options, $columns)
    {
        try {
            $params = $this->buildParams($this->index, $wheres);
            $process = $this->processDistinct($wheres, $options, $columns);
            $sum = 0;
            $count = 0;
            $avg = 0;
            if (!empty($process->data)) {
                foreach ($process->data as $datum) {
                    if (!empty($datum[$columns[0]]) && is_numeric($datum[$columns[0]])) {
                        $count++;
                        $sum += $datum[$columns[0]];
                    }
                }
            }
            if ($count > 0) {
                $avg = $sum / $count;
            }
            
            
            return $this->_return($avg, $process->getMetaData(), $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            
            $result = $this->_returnError($e->getMessage(), $e->getCode(), [], $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
        
    }
    
    private function _matrixDistinctAggregate($wheres, $options, $columns): Results
    {
        return $this->_returnError('Matrix distinct aggregate not supported', 500, [], $this->_queryTag(__FUNCTION__));
    }
    
    
    //======================================================================
    // Private & Sanitization methods
    //======================================================================
    
    
    private function _queryTag($function)
    {
        return str_replace('process', '', $function);
    }
    
    private function _sanitizeSearchResponse($response, $params, $queryTag)
    {
        $meta['timed_out'] = $response['timed_out'];
        $meta['total'] = $response['hits']['total']['value'] ?? 0;
        $meta['max_score'] = $response['hits']['max_score'] ?? 0;
        $data = [];
        if (!empty($response['hits']['hits'])) {
            foreach ($response['hits']['hits'] as $hit) {
                $datum = [];
                $datum['_index'] = $hit['_index'];
                $datum['_id'] = $hit['_id'];
                if (!empty($hit['_source'])) {
                    foreach ($hit['_source'] as $key => $value) {
                        $datum[$key] = $value;
                    }
                }
                $data[] = $datum;
            }
        }
        
        return $this->_return($data, $meta, $params, $queryTag);
    }
    
    private function _sanitizeDistinctResponse($response, $columns, $includeDocCount)
    {
        $keys = [];
        foreach ($columns as $column) {
            $keys[] = 'by_'.$column;
        }
        
        return $this->processBuckets($columns, $keys, $response, 0, $includeDocCount);
        
    }
    
    private function processBuckets($columns, $keys, $response, $index, $includeDocCount, $currentData = [])
    {
        $data = [];
        
        if (!empty($response[$keys[$index]]['buckets'])) {
            foreach ($response[$keys[$index]]['buckets'] as $res) {
                $datum = $currentData;
                $datum[$columns[$index]] = $res['key'];
                if ($includeDocCount) {
                    $datum[$columns[$index].'_count'] = $res['doc_count'];
                }
                
                if (isset($columns[$index + 1])) {
                    $nestedData = $this->processBuckets($columns, $keys, $res, $index + 1, $includeDocCount, $datum);
                    
                    if (!empty($nestedData)) {
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
        
        if ($this->queryLogger && !$this->queryLoggerOnErrorOnly) {
            $this->_logQuery($results);
        }
        
        return $results;
    }
    
    private function _returnError($errorMsg, $errorCode, $params, $queryTag): Results
    {
        $error = new Results([], [], $params, $queryTag);
        $error->setError($errorMsg, $errorCode);
        if ($this->queryLogger) {
            $this->_logQuery($error);
        }
        
        return $error;
    }
    
    private function _logQuery($results)
    {
        $params = [
            'index' => $this->queryLogger,
            'body'  => $results->getLogFormattedMetaData(),
        ];
        try {
            $this->client->index($params);
        } catch (Exception $e) {
            //ignore if problem writing query log
        }
    }
    
}

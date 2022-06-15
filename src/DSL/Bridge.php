<?php

namespace PDPhilip\Elasticsearch\DSL;

use Exception;
use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Aggregation\Matrix\MaxAggregation as MatrixAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\AvgAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\MaxAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\MinAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\SumAggregation;
use ONGR\ElasticsearchDSL\Query\TermLevel\IdsQuery;
use ONGR\ElasticsearchDSL\Search;


class Bridge
{

    use QueryBuilder, IndexInterpreter;

    protected $client;

    protected $queryLogger = false;

    protected $queryLoggerOnErrorOnly = true;

    protected $maxSize = 10; //ES default

    private $index;


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

    public function processSearchRaw($bodyParams)
    {
        $params = [
            'index' => $this->index,
            'body'  => $bodyParams,

        ];
//        dd($params);
        try {
            $process = $this->client->search($params);

            return $this->_sanitizeSearchResponse($process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {

            $error = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($error->errorMessage);
        }
    }

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

    public function processGet($ids, $columns): Results
    {
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $search = new Search();
        $search->addQuery(new IdsQuery($ids));
        $query = $search->toArray();
        $params = [
            'index' => $this->index,
            'body'  => $query,

        ];
        if ($columns && $columns != '*') {
            $params['body']['_source'] = $columns;
        }
        try {
            $process = $this->client->search($params);

            return $this->_sanitizeSearchResponse($process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {

            return $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
        }

    }

    public function processFind($wheres, $options, $columns): Results
    {
//        if (count($wheres) == 1) {
//            if (!empty($wheres['_id'])) {
//                return $this->processGet($wheres['_id'], $columns);
//            }
//        }
        $params = $this->buildParams($this->index, $wheres, $options, $columns);
        return $this->_returnSearch($params,__FUNCTION__);

    }

    public function processSearch($searchParams,$searchOptions,$wheres,$opts,$fields,$cols)
    {
        $params = $this->buildSearchParams($this->index, $searchParams, $searchOptions,$wheres,$opts,$fields,$cols);
        return $this->_returnSearch($params,__FUNCTION__);

    }

    protected function _returnSearch($params,$source)
    {
        if (empty($params['size'])) {
            $params['size'] = $this->maxSize;
        }
        try {
            $process = $this->client->search($params);

            return $this->_sanitizeSearchResponse($process, $params, $this->_queryTag($source));
        } catch (Exception $e) {

            return $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag($source));
        }
    }

    public function processDistinct($column, $wheres): Results
    {
        $col = $column;
        if (is_array($column)) {
            $col = $column[0];
        }
        $params = $this->buildParams($this->index,$wheres);
        $params['body']['aggs']['distinct_'.$col]['terms'] = [
            'field' => $col,
            'size'  => $this->maxSize,
        ];

        try {
            $process = $this->client->search($params);
            $data = [];
            if (!empty($process['aggregations']['distinct_'.$col]['buckets'])) {
                foreach ($process['aggregations']['distinct_'.$col]['buckets'] as $bucket) {
                    $data[] = $bucket['key'];
                }
            }

            return $this->_return($data, $process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {

            return $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
        }


    }

    public function processShowQuery($wheres, $options, $columns)
    {
        $params = $this->buildParams($this->index,$wheres, $options, $columns);

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
//        $data = $this->cleanData($data);
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
            return $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
        }


    }

    public function processInsertOne($values, $refresh): Results
    {
        return $this->processSave($values, $refresh);
    }

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

    public function processIncrementMany($wheres, $newValues, $options, $refresh): Results
    {
        //INC ON Nested Objects?

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
                $response = $this->client->delete($params);
                $response['deleteCount'] = $response['result'] === 'deleted' ? 1 : 0;

                return $this->_return($response['deleteCount'], $response, $params, $this->_queryTag(__FUNCTION__));
            } catch (Exception $e) {
                return $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            }
        }
        try {
            $params = $this->buildParams($this->index, $wheres, $options);
            $response = $this->client->deleteByQuery($params);
            $response['deleteCount'] = $response['deleted'] ?? 0;

            return $this->_return($response['deleteCount'], $response, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            return $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
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

    public function processGetIndices($all)
    {
        $response = $this->client->cat()->indices();

        return $this->catIndices($response, $all);
    }

    public function processIndexExists($index)
    {
        $params = ['index' => $index];

        return $this->client->indices()->exists($params);
    }

    public function processIndexMappings($index): array
    {
        $params = ['index' => $index];
        try {
            $response = $this->client->indices()->getMapping($params);

            $result = $this->_return($response, $response, $params, $this->_queryTag(__FUNCTION__));

            return $result->data;
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
    }

    public function processIndexSettings($index): array
    {
        $params = ['index' => $index];
        try {
            $response = $this->client->indices()->getSettings($params);

            $result = $this->_return($response, $response, $params, $this->_queryTag(__FUNCTION__));

            return $result->data;
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
    }

    public function processIndexCreate($settings): bool
    {

        $params = $this->buildIndexMap($this->index, $settings);

        try {
            $response = $this->client->indices()->create($params);
            $result = $this->_return(true, $response, $params, $this->_queryTag(__FUNCTION__));

            return true;
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }

    }

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

    public function processIndexModify($settings)
    {

        $params = $this->buildIndexMap($this->index, $settings);
        $params['body']['_source']['enabled'] = true;

        try {
            $response = $this->client->indices()->putMapping($params);
            $result = $this->_return(true, $response, $params, $this->_queryTag(__FUNCTION__));

            return true;
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }

    }

    public function processReIndex($newIndex, $oldIndex)
    {
        $params['source']['index'] = $oldIndex;
        $params['dest']['index'] = $newIndex;
        try {
            $response = $this->client->reindex($params);
            $result = $this->_return(true, $response, $params, $this->_queryTag(__FUNCTION__));

            return true;
        } catch (Exception $e) {
            $result = $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
            throw new Exception($result->errorMessage);
        }
    }

    public function processIndexAnalyzerSettings($settings)
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
        $params = $this->buildParams($this->index, $wheres);
        try {
            $process = $this->client->count($params);

            return $this->_return($process['count'] ?? 0, $process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {

            return $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
        }

    }

    private function _maxAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres, $options);

        try {
            $agg = new MaxAggregation('max_value', $columns[0]);
            $params['body']['aggs']['max_value'] = $agg->toArray();
            $process = $this->client->search($params);

            return $this->_return($process['aggregations']['max_value']['value'] ?? 0, $process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {

            return $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
        }


    }

    private function _minAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres, $options);
        try {
            $agg = new MinAggregation('min_value', $columns[0]);
            $params['body']['aggs']['min_value'] = $agg->toArray();
            $process = $this->client->search($params);

            return $this->_return($process['aggregations']['min_value']['value'] ?? 0, $process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {
            return $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
        }


    }

    private function _sumAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index,$wheres, $options);
        try {
            $agg = new SumAggregation('sum_value', $columns[0]);
            $params['body']['aggs']['sum_value'] = $agg->toArray();
            $process = $this->client->search($params);

            return $this->_return($process['aggregations']['sum_value']['value'] ?? 0, $process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {

            return $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
        }

    }

    private function _avgAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index, $wheres, $options);
        try {
            $agg = new AvgAggregation('avg_value', $columns[0]);
            $params['body']['aggs']['avg_value'] = $agg->toArray();
            $process = $this->client->search($params);

            return $this->_return($process['aggregations']['avg_value']['value'] ?? 0, $process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {

            return $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
        }

    }

    private function _matrixAggregate($wheres, $options, $columns): Results
    {
        $params = $this->buildParams($this->index,$wheres, $options);
        try {
            $agg = new MatrixAggregation('sum_value', $columns);
            $params['body']['aggs']['statistics'] = $agg->toArray();
            $process = $this->client->search($params);

            return $this->_return($process['aggregations']['statistics'] ?? [], $process, $params, $this->_queryTag(__FUNCTION__));
        } catch (Exception $e) {

            return $this->_returnError($e->getMessage(), $e->getCode(), $params, $this->_queryTag(__FUNCTION__));
        }

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

    private function _return($data, $meta, $params, $queryTag): Results
    {
        unset($meta['_source']);

        $results = new Results($data, $meta, $params, $queryTag);
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

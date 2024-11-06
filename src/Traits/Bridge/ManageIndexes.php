<?php

  declare(strict_types=1);

  namespace PDPhilip\Elasticsearch\Traits\Bridge;

  use Closure;
  use Elastic\Elasticsearch\Exception\ClientResponseException;
  use Elastic\Elasticsearch\Exception\MissingParameterException;
  use Elastic\Elasticsearch\Exception\ServerResponseException;
  use Exception;
  use Illuminate\Database\QueryException;

  trait ManageIndexes
  {

    use IndexInterpreter;

    public function bootManageIndexes(){

    }

    public function indexExists($index): bool
    {
      $params = ['index' => $index];

      $result = $this->run(
        $this->addClientParams($params),
        [],
        Closure::fromCallable([$this->client->indices(), 'exists'])
      );

      return $result->getStatusCode() == 200;
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function getIndices($all): array
    {
      $index = $this->index;
      if ($all) {
        $index = '*';
      }

      $params = ['index' => $index];

      $result = $this->run(
        $this->addClientParams($params),
        [],
        $this->client->indices()->get(...)
      );

      return $result->asArray();
    }


    /**
     * @throws QueryException
     */
    public function indexSettings($index): array
    {
      $params = ['index' => $index];

      //TODO: FIX THIS!
      $result = $this->run(
        $this->addClientParams($params),
        [],
        Closure::fromCallable([$this->client->indices(), 'getSettings'])
      );


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
    public function indexCreate($settings): bool
    {
      $params = $this->buildIndexMap($this->index, $settings);

      $result = $this->run(
        $this->addClientParams($params),
        [],
        $this->client->indices()->create(...)
      );

      $created = $result->asArray();
      return $created['acknowledged'];
    }

    /**
     * @throws QueryException
     */
    public function indexDelete(): bool
    {
      $params = ['index' => $this->index];

      $result = $this->run(
        $this->addClientParams($params),
        [],
        $this->client->indices()->delete(...)
      );

      return $result->getStatusCode() == 200;
    }

    /**
     * @throws QueryException
     */
    public function indexModify($settings): bool
    {

      $params = $this->buildIndexMap($this->index, $settings);
      $params['body']['_source']['enabled'] = true;
      $props = $params['body']['mappings']['properties'];
      unset($params['body']['mappings']);
      $params['body']['properties'] = $props;

      $result = $this->run(
        $this->addClientParams($params),
        [],
        $this->client->indices()->putMapping(...)
      );

      return $result->getStatusCode() == 200;
    }

    /**
     * @throws QueryException
     */
    public function reIndex($oldIndex, $newIndex): Results
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
    public function indexAnalyzerSettings($settings): bool
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

    /**
     * @throws QueryException
     */
    public function indexMappings($index): array
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

    /**
     * @throws QueryException
     */
    public function fieldMapping(string $index, string|array $field, bool $raw = false): array|Collection
    {
      $params = ['index' => $index, 'fields' => $field];
      $result = [];
      try {
        $responseObject = $this->client->indices()->getFieldMapping($params);
        $response = $responseObject->asArray();
        $result = $this->_return($response, $response, $params, $this->_queryTag(__FUNCTION__));
      } catch (Exception $e) {
        $this->_throwError($e, $params, $this->_queryTag(__FUNCTION__));
      }
      if ($raw) {
        return $result->data;
      }

      return $this->_parseFieldMap($result->data);

    }

  }

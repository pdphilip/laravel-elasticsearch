<?php

  declare(strict_types=1);

  namespace PDPhilip\Elasticsearch\Traits\Bridge;

  use Closure;
  use Elastic\Elasticsearch\Exception\ClientResponseException;
  use Exception;
  use PDPhilip\Elasticsearch\Data\Result;
  use PDPhilip\Elasticsearch\DSL\exceptions\ParameterException;
  use PDPhilip\Elasticsearch\Exceptions\BulkInsertQueryException;
  use PDPhilip\Elasticsearch\Exceptions\QueryException;

  trait Bridgeable
  {
    use ManageIndexes;
    use SanitizeResponses;
    use QueryBuilder;
    use Aggregates;

    protected $requestTimeout;

    public function bootBridgeable(){

    }

    /**
     */
    public function getId($id, $columns, $softDeleteColumn): Result
    {
      $params = [
        'index' => $this->index,
        'id' => $id,
      ];
      if (empty($columns)) {
        $columns = ['*'];
      }
      if (! is_array($columns)) {
        $columns = [$columns];
      }
      $allColumns = $columns[0] == '*';

      if ($softDeleteColumn && ! $allColumns && ! in_array($softDeleteColumn, $columns)) {
        $columns[] = $softDeleteColumn;
      }
      $params['_source'] = $columns;

      try {
        $result = $this->run(
          $this->addClientParams($params),
          [],
          $this->client->get(...)
        );
      } catch (QueryException $e) {
        $e->getCode() == 404 ? $result = null : throw $e;
      }


      return $this->sanitizeGetResponse($result, $params, $softDeleteColumn);
    }



    /**
     * @throws QueryException
     */
    public function save($data): Result
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
        'body' => $data
      ];

      if ($id) {
        $params['id'] = $id;
      }

      $response = [];
      $savedData = [];

      $result = $this->run(
        $this->addClientParams($params),
        [],
        $this->client->index(...)
      );

      $savedData = ['id' => $result['_id']] + $data;

      return new Result($savedData, $response, $params);
    }

    /**
     * @throws ParameterException
     */
    public function find($wheres, $options, $columns): Result
    {
      $params = $this->buildParams($this->index, $wheres, $options, $columns);

      return $this->sanitizeSearchResponse($this->search($params), $params);
    }

      /**
       * Run a select statement against the database.
       *
       * @param  array   $params
       * @param  array   $bindings
       * @return array
       */
      public function search($params, $bindings = [])
      {
        $result = $this->run(
          $this->addClientParams($params),
          $bindings,
          $this->client->search(...)
        );

        return $result->asArray();

      }

    /**
     * @throws QueryException
     * @throws ParameterException
     */
    public function updateMany($wheres, $newValues, $options): Result
    {
      $resultMeta['modified'] = 0;
      $resultMeta['failed'] = 0;
      $resultData = [];
      $data = $this->find($wheres, $options, []);

      if (! empty($data->data)) {
        foreach ($data->data as $currentData) {

          foreach ($newValues as $field => $value) {
            $currentData[$field] = $value;
          }
          $updated = $this->save($currentData);
          if ($updated->isSuccessful()) {
            $resultMeta['modified']++;
            $resultData[] = $updated->data;
          } else {
            $resultMeta['failed']++;
          }
        }
      }

      $params['query'] = $this->buildQuery($wheres);
      $params['queryOptions'] = $options;
      $params['updateValues'] = $newValues;

      return new Result($resultData, $resultMeta, $params);
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  array  $params
     * @param  array  $bindings
     * @return int
     */
    public function delete($params, $bindings = [])
    {
      $deleteMethod = isset($params['body']['query']) ? 'deleteByQuery' : 'delete';
      $result = $this->run(
        $this->addClientParams($params),
        [],
        $this->client->$deleteMethod(...)
      );

      return $result->getStatusCode() == 200 ? 1 : 0;

    }


    /**
     * Run an insert statement against the database.
     *
     * @param array $params
     * @param array $bindings
     * @return bool
     * @throws BulkInsertQueryException
     */
    public function insert($params, $bindings = [])
    {
      $result = $this->run(
        $this->addClientParams($params),
        $bindings,
        $this->client->bulk(...)
      );

      if (!empty($result['errors'])) {
        throw new BulkInsertQueryException($result->asArray());
      }

      return true;
    }

    /**
     * Add client-specific parameters to the request params
     *
     * @param array $params
     *
     * @return array
     */
    protected function addClientParams(array $params): array
    {
      if ($this->requestTimeout) {
        $params['client']['timeout'] = $this->requestTimeout;
      }

      return $params;
    }

  }

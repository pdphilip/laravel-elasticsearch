<?php

declare(strict_types=1);

  namespace PDPhilip\Elasticsearch\Traits\Bridge;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Data\Result;
use PDPhilip\Elasticsearch\Exceptions\ParameterException;
use PDPhilip\Elasticsearch\Exceptions\QueryException;
use PDPhilip\Elasticsearch\Helpers\ParameterBuilder;

trait Aggregates
{

  //----------------------------------------------------------------------
  // Aggregates
  //----------------------------------------------------------------------
  /**
   * @throws QueryException
   * @throws ParameterException
   */
  public function multipleAggregate($functions, $wheres, $options, $column): Result
  {
    $params = $this->buildParams($this->index, $wheres, $options);
    $params['body']['aggs'] = ParameterBuilder::multipleAggregations($functions, $column);

    $result = $this->run(
      $this->addClientParams($params),
      [],
      $this->client->search(...)
    );

    return new Result($result['aggregations'] ?? [], $result, $params);
  }

  /**
   *  Aggregate entry point
   */
  public function aggregate($function, $wheres, $options, $columns): Result
  {
    return $this->{$function.'Aggregate'}($wheres, $options, $columns);
  }

  /**
   */
  public function countAggregate($wheres, $options, $columns): Result
  {
    $params = $this->buildParams($this->index, $wheres);

    $params = [...$params, ...$options];

    $result = $this->run(
      $this->addClientParams($params),
      [],
      $this->client->count(...)
    );

    return new Result($result['count'] ?? 0, $result->asArray(), $params);
  }

  /**
   * @throws ParameterException
   * @throws QueryException
   */
  private function maxAggregate($wheres, $options, $columns): Results
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
  private function minAggregate($wheres, $options, $columns): Results
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
  private function sumAggregate($wheres, $options, $columns): Results
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
  private function avgAggregate($wheres, $options, $columns): Results
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
  private function matrixAggregate($wheres, $options, $columns): Results
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
}

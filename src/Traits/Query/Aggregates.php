<?php

declare(strict_types=1);

  namespace PDPhilip\Elasticsearch\Traits\Query;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Data\Aggregation;
use PDPhilip\Elasticsearch\Data\Result;
use PDPhilip\Elasticsearch\Exceptions\ParameterException;
use PDPhilip\Elasticsearch\Exceptions\QueryException;
use PDPhilip\Elasticsearch\Helpers\ParameterBuilder;



trait Aggregates
{
  use AggregatesGrammar;


  public $aggregations;


  public function aggregate($function, $columns = ['*'])
  {

    return match($function){
      'count' => $this->aggregateCount($columns),
      'sum' => $this->aggregateSum($columns),
      'cake' => 'This food is a cake',
    };


  }

  public function aggregateCount($columns): int
  {
    $params = $this->toSql();
    $result = $this->connection->count(['index' => $params['index']]);
    return $result->asArray()['count'];
  }

  public function aggregateSum($columns): int
  {

    $result = $this->connection->search($this->grammar->compileAggregation($this, [
      'key' => reset($columns),
      'type' => 'sum'
    ]), []);


    $result = $result->asArray();

    return (int) $result['aggregations'][reset($columns)]['value'];
  }

  /**
   * @param string $key
   * @param string $type
   * @param null   $args
   * @param null   $aggregations
   * @return self
   */
  public function aggregation($key, $type = null, $args = null, $aggregations = null): self
  {
    if ($key instanceof Aggregation) {
      $aggregation = $key;

      $this->aggregations[] = [
        'key'          => $aggregation->getKey(),
        'type'         => $aggregation->getType(),
        'args'         => $aggregation->getArguments(),
        'aggregations' => $aggregation($this->newQuery()),
      ];

      return $this;
    }

    if (!is_string($args) && is_callable($args)) {
      call_user_func($args, $args = $this->newQuery());
    }

    if (!is_string($aggregations) && is_callable($aggregations)) {
      call_user_func($aggregations, $aggregations = $this->newQuery());
    }

    $this->aggregations[] = compact(
      'key',
      'type',
      'args',
      'aggregations'
    );

    return $this;
  }

  /**
   * Get the aggregations returned from query
   *
   * @return array
   */
  public function getAggregationResults(): array
  {
    $this->getResultsOnce();

    return $this->processor->getAggregationResults();
  }

}

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
use PDPhilip\Elasticsearch\Query\Builder;

trait AggregatesGrammar
{
  /**
   * Compile where clauses for a query
   *
   * @param  Builder  $builder
   * @return array
   */
  public function compileAggregateSum(string $column, Builder $builder): array
  {
    return [
      'aggs' => [
        'total_sum' => [
          'sum' => [
            'script' => [
              'source' => "doc.containsKey('$column') && !doc['$column'].empty ? doc['$column'].value : 0"
            ]
          ]
        ]
      ]
    ];
  }

}

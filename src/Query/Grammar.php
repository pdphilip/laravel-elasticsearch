<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query;


use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Enums\WaitFor;

class Grammar extends BaseGrammar
{
  /**
   * The index suffix.
   *
   * @var string
   */
  protected $indexSuffix = '';

  /**
   * Compile the given values to an Elasticsearch insert statement
   *
   * @param  Builder  $builder
   * @param  array  $values
   * @return array
   */
  public function compileInsert(Builder $builder, array $values): array
  {
    $params = [];

    if (! is_array(reset($values))) {
      $values = [$values];
    }

    foreach ($values as $doc) {
      $doc['id'] = $doc['id'] ?? ((string) Str::orderedUuid());

      $index = [
        '_index' => $builder->from . $this->indexSuffix,
        '_id'    => $doc['id'],
      ];

      $params['body'][] = ['index' => $index];

      foreach($doc as &$property) {
        $property = $this->getStringValue($property);
      }

      $params['body'][] = $doc;
    }

    $options = $this->compileOptions($builder);
    $options['refresh'] = $options['refresh']->get();

    return [
      ...$params,
      ...$options
    ];
  }

  /**
   * Compile a delete query
   *
   * @param  Builder  $builder
   * @return array
   */
  public function compileDelete(Builder $builder): array
  {
    $clause = $this->compileSelect($builder);

    if ($conflict = $builder->getOption('delete_conflicts')) {
      $clause['conflicts'] = $conflict;
    }

    if ($refresh = $builder->getOption('delete_refresh')) {
      $clause['refresh'] = $refresh;
    }

    return $clause;
  }

  /**
   * @param Builder $builder
   *
   * @return mixed
   */
  protected function compileOptions(Builder $builder): array
  {
    $options = [];

    // We always wait for refresh. However
    if ($waitFor = $builder->getOption('waitForRefresh')) {
      $options['refresh'] = $waitFor;
    } else {
      $options['refresh'] = WaitFor::WAITFOR;
    }

    return $options;
  }

  /**
   * @param $value
   * @return mixed
   */
  protected function getStringValue($value)
  {
    // Convert DateTime values to UTCDateTime.
    if ($value instanceof DateTime) {
      $value = $this->convertDateTime($value);
    } else {
        if (is_array($value)) {
          foreach ($value as &$val) {
            if ($val instanceof DateTime) {
              $val = $this->convertDateTime($val);
            }
          }
        }

    }
    return $value;
  }

  /**
   * Compile a delete query
   *
   * @param  Builder  $builder
   * @return string
   */
  protected function convertDateTime($value): string
  {
    if (is_string($value)) {
      return $value;
    }

    return $value->format('c');
  }
}

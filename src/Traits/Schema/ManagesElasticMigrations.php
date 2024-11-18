<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Traits\Schema;

use PDPhilip\Elasticsearch\Schema\Definitions\PropertyDefinition;

trait ManagesElasticMigrations
{

  /**
   * Create a new date column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/date.html
   *
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function date($name, array $parameters = []): PropertyDefinition
  {
    return $this->addColumn('date', $name, $parameters);
  }

  /**
   * Create a new date_range column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/range.html
   *
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function dateRange(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->range('date_range', $name, $parameters);
  }

  /**
   * Create a new double_range column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/range.html
   *
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function doubleRange(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->range('double_range', $name, $parameters);
  }

  /**
   * Create a new float_range column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/range.html
   *
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function floatRange(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->range('float_range', $name, $parameters);
  }

  /**
   * Create a new geo_point column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/geo-point.html
   *
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function geoPoint(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->addColumn('geo_point', $name, $parameters);
  }

  /**
   * Create a new geo_shape column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/geo-shape.html
   *
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function geoShape(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->addColumn('geo_shape', $name, $parameters);
  }

  /**
   * Create a new integer_range column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/range.html
   *
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function integerRange(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->range('integer_range', $name, $parameters);
  }

  /**
   * Create a new range column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/range.html
   *
   * @param string $type
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function range(string $type, string $name, array $parameters = []): PropertyDefinition
  {
    return $this->addColumn($type, $name, $parameters);
  }

  /**
   * Create a new ip column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/ip.html
   *
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function ip(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->ipAddress($name, $parameters);
  }

  /**
   * Create a new ip_range column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/range.html
   *
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function ipRange(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->range('ip_range', $name, $parameters);
  }

  /**
   * Create a new join column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/parent-join.html
   *
   * @param string $name
   * @param array  $relations
   *
   * @return PropertyDefinition
   */
  public function join(string $name, array $relations): PropertyDefinition
  {
    return $this->addColumn('join', $name, compact('relations'));
  }

  /**
   * Create a new long column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/number.html
   *
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function long(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->addColumn('long', $name, $parameters);
  }

  /**
   * Create a new long_range column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/range.html
   *
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function longRange(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->range('long_range', $name, $parameters);
  }

  /**
   * Create a new nested column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/nested.html
   *
   * @param string $name
   *
   * @return PropertyDefinition
   */
  public function nested(string $name): PropertyDefinition
  {
    return $this->addColumn('nested', $name);
  }

  /**
   * Create a new percolator column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/percolator.html
   *
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function percolator(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->addColumn('percolator', $name, $parameters);
  }

  /**
   * @param string $name
   * @param bool   $hasKeyword adds a keyword subfield.
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function text($name, bool $hasKeyword = false, array $parameters = []): PropertyDefinition
  {
    if (! $hasKeyword) {
      return $this->addColumn('text', $name, $parameters);
    }

    return $this->addColumn('text', $name, $parameters)->fields(function ($field) {
      $field->keyword('keyword', ['ignore_above' => 256]);
    });
  }

  /**
   * Create a new keyword column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/keyword.html
   *
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function keyword(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->addColumn('keyword', $name, $parameters);
  }

  /**
   * Create a new token_count column on the table.
   *
   * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/token-count.html
   *
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function tokenCount(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->addColumn('token_count', $name, $parameters);
  }

}

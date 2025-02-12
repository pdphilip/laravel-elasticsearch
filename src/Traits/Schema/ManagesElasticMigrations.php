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
     * @param  string  $name
     */
    public function date($name, array|string $parameters = []): PropertyDefinition
    {
        if (is_string($parameters)) {
            $parameters = ['format' => $parameters];
        }

        return $this->addColumn('date', $name, $parameters);
    }

    /**
     * Create a new date_range column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/range.html
     */
    public function dateRange(string $name, array|string $parameters = []): PropertyDefinition
    {
        if (is_string($parameters)) {
            $parameters = ['format' => $parameters];
        }

        return $this->range('date_range', $name, $parameters);
    }

    /**
     * Create a new double_range column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/range.html
     */
    public function doubleRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('double_range', $name, $parameters);
    }

    /**
     * Create a new float_range column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/range.html
     */
    public function floatRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('float_range', $name, $parameters);
    }

    /**
     * Create a new geo_point column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/geo-point.html
     */
    public function geoPoint(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('geo_point', $name, $parameters);
    }

    /**
     * Create a new geo_shape column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/geo-shape.html
     */
    public function geoShape(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('geo_shape', $name, $parameters);
    }

    /**
     * Create a new integer_range column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/range.html
     */
    public function integerRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('integer_range', $name, $parameters);
    }

    /**
     * Create a new range column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/range.html
     */
    public function range(string $type, string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn($type, $name, $parameters);
    }

    /**
     * Create a new ip column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/ip.html
     */
    public function ip(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->ipAddress($name, $parameters);
    }

    /**
     * Create a new ip_range column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/range.html
     */
    public function ipRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('ip_range', $name, $parameters);
    }

    /**
     * Create a new join column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/parent-join.html
     */
    public function join(string $name, array $relations): PropertyDefinition
    {
        return $this->addColumn('join', $name, compact('relations'));
    }

    /**
     * Create a new long column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/number.html
     */
    public function long(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('long', $name, $parameters);
    }

    /**
     * Create a new long_range column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/range.html
     */
    public function longRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('long_range', $name, $parameters);
    }

    /**
     * Create a new nested column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/nested.html
     */
    public function nested(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('nested', $name, $parameters);
    }

    /**
     * Create a new nested column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/flattened.html
     */
    public function flattened(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('flattened', $name, $parameters);
    }

    /**
     * Create a new percolator column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/percolator.html
     */
    public function percolator(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('percolator', $name, $parameters);
    }

    /**
     * @param  string  $name
     * @param  bool  $hasKeyword  adds a keyword subfield.
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
     */
    public function keyword(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('keyword', $name, $parameters);
    }

    /**
     * Create a new token_count column on the table.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/token-count.html
     */
    public function tokenCount(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('token_count', $name, $parameters);
    }

    /**
     *  Alias field
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/alias.html
     */
    public function aliasField(string $field, string $path): PropertyDefinition
    {
        return $this->addColumn('alias', $field, ['path' => $path]);
    }

    // Same as Add Col but with $name and $type reversed for better readability.
    public function addField(string $name, string $type, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn($type, $name, $parameters);
    }

    // ----------------------------------------------------------------------
    // Porting from V4
    // ----------------------------------------------------------------------

    public function short(string $field, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('short', $field, $parameters);
    }

    public function byte(string $field, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('byte', $field, $parameters);
    }

    //    public function double(string $field, array $parameters = []): PropertyDefinition
    //    {
    //        return $this->addColumn('double', $field, $parameters);
    //    }

    //    public function float(string $field, array $parameters = []): PropertyDefinition
    //    {
    //        return $this->addColumn('float', $field, $parameters);
    //    }

    public function halfFloat(string $field, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('half_float', $field, $parameters);
    }

    public function scaledFloat(string $field, $scalingFactor = 100, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('scaled_float', $field, array_merge($parameters, ['scaling_factor' => $scalingFactor]));
    }

    public function unsignedLong(string $field, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('unsigned_long', $field, $parameters);
    }
}

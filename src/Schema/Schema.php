<?php

namespace PDPhilip\Elasticsearch\Schema;

use Illuminate\Support\Facades\Facade;

/**
 * @method static Builder getIndices(bool $all = null)
 * @method static Builder getMappings(string $index)
 * @method static Builder getSettings(string $index)
 * @method static Builder create(string $index, \Closure $callback)
 * @method static Builder createIfNotExists(string $index, \Closure $callback)
 * @method static Builder reIndex(string $from, string $to)
 * @wip static Builder rename(string $from, string $to)
 * @method static Builder modify(string $index, \Closure $callback)
 * @method static Builder delete(string $index)
 * @method static Builder deleteIfExists(string $index)
 * @method static Builder setAnalyser(string $index, \Closure $callback)
 *
 * @wip static Builder createTemplate(string $name, \Closure $callback)
 * @method static bool hasField(string $index, string $field)
 * @method static bool hasFields(string $index, array $fields)
 * @method static bool hasIndex(string $index)
 * @method static bool dsl(string $method, array $parameters)
 * @method static \PDPhilip\Elasticsearch\Connection getConnection()
 * @method static \PDPhilip\Elasticsearch\Schema\Builder setConnection(\PDPhilip\Elasticsearch\Connection $connection)
 *
 * @see \PDPhilip\Elasticsearch\Schema\Builder
 */
class Schema extends Facade
{
    /**
     * Get a schema builder instance for a connection.
     *
     * @param    string|null    $name
     *
     * @return Builder
     */
    public static function connection($name)
    {
        return static::$app['db']->connection($name)->getSchemaBuilder();
    }

    /**
     * Get a schema builder instance for the default connection.
     *
     * @return Builder
     */
    protected static function getFacadeAccessor()
    {
        return static::$app['db']->connection('elasticsearch')->getSchemaBuilder();
    }
}

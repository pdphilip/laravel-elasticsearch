<?php

namespace PDPhilip\Elasticsearch\Schema;

use PDPhilip\Elasticsearch\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Builder overridePrefix(string|null $value)
 * @method static array getIndex(string $index)
 * @method static array getIndices()
 * @method static array getMappings(string $index)
 * @method static array getSettings(string $index)
 * @method static array create(string $index, \Closure $callback)
 * @method static array createIfNotExists(string $index, \Closure $callback)
 * @method static Builder reIndex(string $from, string $to)
 * @wip static Builder rename(string $from, string $to)
 * @method static Builder modify(string $index, \Closure $callback)
 * @method static bool delete(string $index)
 * @method static bool deleteIfExists(string $index)
 * @method static array setAnalyser(string $index, \Closure $callback)
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
//    protected static $app;
//
//    protected static $resolvedInstance;
//
//
//    protected static $cached = false;
    /**
     * Get a schema builder instance for a connection.
     *
     * @param    string|null    $name
     *
     * @return Builder
     */
    public static function connection($name)
    {

        if ($name === null) {
            return static::getFacadeAccessor();
        }

        return static::$app['db']->connection($name)->getSchemaBuilder();
    }

    public static function on($name)
    {
        return static::connection($name);
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

//
    public static function __callStatic($method, $args)
    {
        $instance = static::getFacadeAccessor();

        return $instance->$method(...$args);
    }

}

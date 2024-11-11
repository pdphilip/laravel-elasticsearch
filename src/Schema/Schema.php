<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin Builder
 *
 * @see \PDPhilip\Elasticsearch\Schema\Builder
 */
class Schema extends Facade
{
    public static function on($name): Builder
    {
        return static::connection($name);
    }

    /**
     * Get a schema builder instance for a connection.
     *
     * @param  string|null  $name
     */
    public static function connection($name): Builder
    {

        if ($name === null) {
            return static::getFacadeAccessor();
        }

        return static::$app['db']->connection($name)->getSchemaBuilder();
    }

    /**
     * Get a schema builder instance for the default connection.
     */
    protected static function getFacadeAccessor(): Builder
    {
        return static::$app['db']->connection('elasticsearch')->getSchemaBuilder();
    }

    public static function __callStatic($method, $args)
    {
        $instance = static::getFacadeAccessor();

        return $instance->$method(...$args);
    }
}

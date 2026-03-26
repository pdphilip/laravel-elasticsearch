<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema;

use Illuminate\Support\Facades\Facade;
use PDPhilip\Elasticsearch\Exceptions\LogicException;

/**
 * @method static Builder overridePrefix(string $value)
 * @method static array getTables()
 * @method static array getTable($name)
 * @method static bool hasTable($table)
 * @method static bool hasColumn($table, $column)
 * @method static bool hasColumns($table, $columns)
 * @method static void table($table, $callback)
 * @method static void index($table, $callback)
 * @method static void create($table, $callback)
 * @method static void createIfNotExists($table, $callback)
 * @method static void drop($table)
 * @method static void dropIfExists($table)
 * @method static array getIndex($indices)
 * @method static array getIndices()
 * @method static array getIndicesSummary()
 * @method static array getFieldMapping($table, $column, $raw = false)
 * @method static array getFieldsMapping($table, $raw = false)
 * @method static array getMappings($table, $raw = false)
 * @method static array getSettings($table)
 * @method static bool indexExists($table)
 * @method static array reindex($from, $to, $options = [])
 * @method static mixed indices()
 * @method static void modify($index, $callback)
 * @method static void delete($index)
 * @method static void deleteIfExists($index)
 * @method static void setAnalyser($index, $callback)
 * @method static bool hasField($index, $field)
 * @method static bool hasFields($index, $fields)
 * @method static LogicException hasIndex($table, $index, $type = null)
 *** Laravel Inherited
 * @method static \Illuminate\Database\Connection getConnection()
 * @method static void blueprintResolver(\Closure $resolver)
 *
 * @see Builder
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

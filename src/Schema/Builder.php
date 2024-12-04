<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema;

use Closure;
use Illuminate\Database\Schema\Builder as BaseBuilder;
use Illuminate\Support\Arr;

class Builder extends BaseBuilder
{
    public function index($table, Closure $callback)
    {
        $this->table($table, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function create($table, ?Closure $callback = null)
    {
        $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($callback) {
            $blueprint->create();

            if ($callback) {
                $callback($blueprint);
            }
        }));
    }

    /**
     * Run a reindex statement against the database.
     *
     */
    public function reindex($from, $to, $options = [])
    {
      $params = ['body' => [
        'source' => ['index' => $from]
        , 'dest' => ['index' => $to]
      ]
      ]
      ;
      $params = [...$params, ...$options];

      return $this->connection->reindex($params)->asArray();
    }

    /**
     * Create a new table if it doesn't already exist on the schema.
     *
     * @param  string  $table
     * @return void
     */
    public function createIfNotExists($table, ?Closure $callback = null)
    {
        $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($callback) {
            $blueprint->createIfNotExists();

            if ($callback) {
                $callback($blueprint);
            }
        }));
    }

    public function getTables()
    {
        return $this->connection->getPostProcessor()->processTables(
            $this->connection->cat()->indices(['format' => 'JSON'])
        );
    }

    /**
     * Returns the mapping details about your indices.
     *
     * @param  string  $table
     * @param  string  $column
     */
    public function getFieldMapping($table, $column): array
    {
        $params = ['index' => $table, 'fields' => $column];

        return $this->connection->indices()->getFieldMapping($params)->asArray();
    }

    /**
     * Returns the mapping details about your indices.
     *
     * @param  string|array  $table
     */
    public function getMappings($table): array
    {
        $params = ['index' => Arr::wrap($table)];

        return $this->connection->indices()->getMapping($params)->asArray();
    }

    /**
     * Shows you the currently configured settings for one or more indices
     *
     * @param  string|array  $table
     */
    public function getSettings($table): array
    {
        $params = ['index' => Arr::wrap($table)];

        return $this->connection->indices()->getSettings($params)->asArray();
    }

    public function hasColumn($table, $column): bool
    {
        $params = ['index' => $table, 'fields' => $column];
        $result = $this->connection->indices()->getFieldMapping($params)->asArray();

        return ! empty($result[$table]['mappings'][$column]);
    }

    public function hasColumns($table, $columns): bool
    {

        $params = ['index' => $table, 'fields' => implode(',', $columns)];
        $result = $this->connection->indices()->getFieldMapping($params)->asArray();

        foreach ($columns as $value) {
            if (empty($result[$table]['mappings'][$value])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  string  $table
     */
    public function table($table, Closure $callback)
    {
        $this->build(tap($this->createBlueprint($table), function (Blueprint $blueprint) use ($callback) {
            $blueprint->update();

            $callback($blueprint);
        }));
    }

    /**
     * {@inheritDoc}
     */
    protected function createBlueprint($table, ?Closure $callback = null)
    {
        return new Blueprint($table, $callback);
    }
}

<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema;

use Closure;
use Illuminate\Database\Schema\Builder as BaseBuilder;

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

    public function getTables()
    {
        return $this->connection->getPostProcessor()->processTables(
              $this->connection->cat()->indices(['format' => 'JSON'])
        );
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

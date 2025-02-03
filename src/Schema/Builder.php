<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema;

use Closure;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Illuminate\Database\Schema\Builder as BaseBuilder;
use Illuminate\Support\Arr;
use PDPhilip\Elasticsearch\Connection;

/**
 * @property Connection $connection
 */
class Builder extends BaseBuilder
{
    //----------------------------------------------------------------------
    // Index Config & Reads
    //----------------------------------------------------------------------

    public function overridePrefix($value): Builder
    {
        $this->connection->setIndexPrefix($value);

        return $this;
    }

    //----------------------------------------------------------------------
    // Index getters
    //----------------------------------------------------------------------
    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function getTables(): array
    {
        return $this->connection->getPostProcessor()->processTables(
            $this->connection->cat()->indices(
                [
                    'index' => $this->connection->getTablePrefix().'*',
                    'format' => 'json',
                ]
            )
        );
    }

    /**
     *  Replacement for v4 getIndex()
     *  Includes settings and mappings
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function getIndices(string|array $indices = '*'): array
    {
        $params = ['index' => $this->parseIndexName($indices)];

        return $this->connection->indices()->get($params)->asArray();
    }

    /**
     *  Returns the mapping details about your indices.
     *
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     */
    public function getFieldMapping(string $table, string $column): array
    {
        $params = ['index' => $this->parseIndexName($table), 'fields' => $column];

        return $this->connection->indices()->getFieldMapping($params)->asArray();
    }

    /**
     * Returns the mapping details about your indices.
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function getMappings(string|array $table): array
    {
        $index = $this->parseIndexName($table);
        $params = ['index' => Arr::wrap($index)];

        return $this->connection->indices()->getMapping($params)->asArray();
    }

    /**
     * Shows you the currently configured settings for one or more indices
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function getSettings(string|array $table): array
    {
        $index = $this->parseIndexName($table);
        $params = ['index' => Arr::wrap($index)];

        return $this->connection->indices()->getSettings($params)->asArray();
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function hasTable($table): bool
    {
        $index = $this->parseIndexName($table);
        $params = ['index' => $index];

        return $this->connection->indices()->exists($params)->asBool();

    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     */
    public function hasColumn($table, $column): bool
    {
        $index = $this->parseIndexName($table);
        $params = ['index' => $index, 'fields' => $column];
        $result = $this->connection->indices()->getFieldMapping($params)->asArray();

        return ! empty($result[$index]['mappings'][$column]);
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     */
    public function hasColumns($table, $columns): bool
    {
        $index = $this->parseIndexName($table);
        $params = ['index' => $index, 'fields' => implode(',', $columns)];
        $result = $this->connection->indices()->getFieldMapping($params)->asArray();

        foreach ($columns as $value) {
            if (empty($result[$index]['mappings'][$value])) {
                return false;
            }
        }

        return true;
    }

    //----------------------------------------------------------------------
    // Index Modifiers
    //----------------------------------------------------------------------

    public function table($table, Closure $callback): void
    {
        $index = $this->parseIndexName($table);
        $this->build(tap($this->createBlueprint($index), function (Blueprint $blueprint) use ($callback) {
            $blueprint->update();
            $callback($blueprint);
        }));
    }

    public function index($table, Closure $callback): void
    {
        $this->table($table, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function create($table, ?Closure $callback = null): void
    {
        $index = $this->parseIndexName($table);
        $this->build(tap($this->createBlueprint($index), function ($blueprint) use ($callback) {
            $blueprint->create();

            if ($callback) {
                $callback($blueprint);
            }
        }));
    }

    /**
     * Create a new table if it doesn't already exist on the schema.
     */
    public function createIfNotExists(string $table, ?Closure $callback = null): void
    {
        $index = $this->parseIndexName($table);
        $this->build(tap($this->createBlueprint($index), function (Blueprint $blueprint) use ($callback) {
            $blueprint->createIfNotExists();

            if ($callback) {
                $callback($blueprint);
            }
        }));
    }

    /**
     * {@inheritdoc}
     */
    public function drop($table)
    {
        $index = $this->parseIndexName($table);
        $this->connection->dropIndex($index);
    }

    /**
     * {@inheritdoc}
     */
    public function dropIfExists($table)
    {
        if ($this->hasTable($table)) {
            $this->drop($table);
        }
    }

    /**
     * Run a reindex statement against the database.
     */
    public function reindex($from, $to, $options = [])
    {
        $params = [
            'body' => [
                'source' => ['index' => $from],
                'dest' => ['index' => $to],
            ],
        ];
        $params = [...$params, ...$options];

        return $this->connection->reindex($params)->asArray();
    }

    //----------------------------------------------------------------------
    // protected Methods
    //----------------------------------------------------------------------

    /**
     * {@inheritDoc}
     */
    protected function createBlueprint($table, ?Closure $callback = null): Blueprint
    {
        return new Blueprint($table, $callback);
    }

    protected function parseIndexName(string|array $index): string|array
    {
        if (is_array($index)) {
            return collect($index)->map(function ($item) {
                return $this->connection->getIndexPrefix().$item;
            })->toArray();
        }

        return $this->connection->getIndexPrefix().$index;
    }
}

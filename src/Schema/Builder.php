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
use PDPhilip\Elasticsearch\Helpers\Sanitizer;

/**
 * @property Connection $connection
 */
class Builder extends BaseBuilder
{
    // ----------------------------------------------------------------------
    // Index Config & Reads
    // ----------------------------------------------------------------------

    public function overridePrefix($value): Builder
    {
        $this->connection->setIndexPrefix($value);

        return $this;
    }

    // ----------------------------------------------------------------------
    // Index getters
    // ----------------------------------------------------------------------
    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function getTables(): array
    {
        return $this->connection->getPostProcessor()->processTables(
            $this->connection->elasticClient()->cat()->indices(
                [
                    'index' => $this->connection->getTablePrefix().'*',
                    'format' => 'json',
                ]
            )
        );
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function getTable($name): array
    {
        return $this->connection->getPostProcessor()->processTables(
            $this->connection->elasticClient()->cat()->indices(
                [
                    'index' => $this->connection->getTablePrefix().$name,
                    'format' => 'json',
                ]
            )
        );
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

        return $this->connection->elasticClient()->indices()->exists($params)->asBool();

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
        $result = $this->connection->elasticClient()->indices()->getFieldMapping($params)->asArray();

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
        $result = $this->connection->elasticClient()->indices()->getFieldMapping($params)->asArray();

        foreach ($columns as $value) {
            if (empty($result[$index]['mappings'][$value])) {
                return false;
            }
        }

        return true;
    }

    // ----------------------------------------------------------------------
    // Index Modifiers
    // ----------------------------------------------------------------------

    public function table($table, Closure $callback): void
    {
        $index = $this->parseIndexName($table);
        $this->build(tap($this->createBlueprint($index), function (Blueprint $blueprint) use ($callback) {
            $blueprint->update();
            $callback($blueprint);
        }));
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
    public function dropIfExists($table): void
    {
        try {
            if ($this->hasTable($table)) {
                $this->drop($table);
            }
        } catch (ClientResponseException|MissingParameterException|ServerResponseException $e) {

        }
    }

    // ----------------------------------------------------------------------
    // ES Methods and Aliases
    // ----------------------------------------------------------------------

    public function index($table, Closure $callback): void
    {
        $this->table($table, $callback);
    }

    /**
     *  Includes settings and mappings
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function getIndex(string|array $indices = '*'): array
    {
        $params = ['index' => $this->parseIndexName($indices)];

        return $this->connection->elasticClient()->indices()->get($params)->asArray();
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     */
    public function getIndices(): array
    {
        return $this->getIndex();
    }

    /**
     *  Returns the mapping details about your indices.
     *
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     */
    public function getFieldMapping(string $table, string $column, $flatten = false): array
    {
        $mapping = $this->connection->getFieldMapping($this->parseIndexName($table), $column);
        if (! $flatten) {
            return $mapping;
        }
        $mapping = reset($mapping);

        return Sanitizer::flattenFieldMapping($mapping);

    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function getFieldsMapping(string $table, $flatten = false): array
    {
        return $this->getFieldMapping($table, '*', $flatten);

    }

    /**
     * Returns the mapping details about your indices.
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function getMappings(string|array $table, $flattenProperties = false): array
    {
        $index = $this->parseIndexName($table);
        $params = ['index' => Arr::wrap($index)];

        $mappings = $this->connection->elasticClient()->indices()->getMapping($params)->asArray();
        if (! $flattenProperties) {
            return $mappings;
        }
        $mappings = reset($mappings);
        $mappings = Arr::get($mappings, 'mappings');

        return Sanitizer::flattenMappingProperties($mappings);
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

        return $this->connection->elasticClient()->indices()->getSettings($params)->asArray();
    }

    /**
     * Replaces `hasIndex` method.
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function indexExists($index): bool
    {
        return $this->hasTable($index);
    }

    /**
     * Run a reindex statement against the database.
     */
    public function reindex($from, $to, $options = []): array
    {
        $from = $this->parseIndexName($from);
        $to = $this->parseIndexName($to);
        $params = [
            'body' => [
                'source' => ['index' => $from],
                'dest' => ['index' => $to],
            ],
        ];
        $params = [...$params, ...$options];

        return $this->connection->elasticClient()->reindex($params)->asArray();
    }

    // ----------------------------------------------------------------------
    // V4 Backwards Compatibility
    // ----------------------------------------------------------------------

    /**
     * Run a reindex statement against the database.
     */
    public function modify($index, Closure $callback): void
    {
        $this->table($index, $callback);
    }

    public function delete($index): void
    {
        $this->drop($index);
    }

    public function deleteIfExists($index): void
    {
        $this->dropIfExists($index);
    }

    public function setAnalyser($index, Closure $callback): void
    {
        $this->table($index, $callback);
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     */
    public function hasField($index, $field): bool
    {
        return $this->hasColumn($index, $field);
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     */
    public function hasFields($index, $fields): bool
    {
        return $this->hasColumns($index, $fields);
    }

    // ----------------------------------------------------------------------
    // Protected Methods
    // ----------------------------------------------------------------------

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
                return $this->attachPrefix($item);
            })->toArray();
        }

        return $this->attachPrefix($index);
    }

    protected function attachPrefix(string $index): string
    {
        $prefix = $this->connection->getIndexPrefix();
        if ($prefix && ! str_starts_with($index, $prefix)) {
            $index = $prefix.$index;
        }

        return $index;
    }
}

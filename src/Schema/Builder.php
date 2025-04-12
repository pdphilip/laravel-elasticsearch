<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema;

use Closure;
use Elastic\Elasticsearch\Endpoints\Indices;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Illuminate\Database\Schema\Builder as BaseBuilder;
use Illuminate\Support\Arr;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Exceptions\LogicException;
use PDPhilip\Elasticsearch\Laravel\Compatibility\Schema\BuilderCompatibility;
use PDPhilip\Elasticsearch\Utils\Sanitizer;

/**
 * @property Connection $connection
 */
class Builder extends BaseBuilder
{
    use BuilderCompatibility;
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
    public function getTables($schema = null): array
    {
        return $this->connection->getPostProcessor()->processTables(
            $this->connection->elastic()->cat()->indices(
                [
                    'index' => $this->connection->getTablePrefix().'*',
                    'format' => 'json',
                ]
            )
        );
    }

    public function getTable($name): array
    {
        return $this->connection->getPostProcessor()->processTables(
            $this->connection->elastic()->cat()->indices(
                [
                    'index' => $this->connection->getTablePrefix().$name,
                    'format' => 'json',
                ]
            )
        );
    }

    public function hasTable($table): bool
    {
        $index = $this->parseIndexName($table);
        $params = ['index' => $index];

        return $this->connection->elastic()->indices()->exists($params)->asBool();
    }

    public function hasColumn($table, $column): bool
    {
        $index = $this->parseIndexName($table);
        $params = ['index' => $index, 'fields' => $column];
        $result = $this->connection->elastic()->indices()->getFieldMapping($params)->asArray();

        return ! empty($result[$index]['mappings'][$column]);
    }

    public function hasColumns($table, $columns): bool
    {
        $index = $this->parseIndexName($table);
        $params = ['index' => $index, 'fields' => implode(',', $columns)];
        $result = $this->connection->elastic()->indices()->getFieldMapping($params)->asArray();

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
     */
    public function getIndex(string|array $indices = '*'): array
    {
        $params = ['index' => $this->parseIndexName($indices)];

        return $this->connection->elastic()->indices()->get($params)->asArray();
    }

    /**
     * Returns the list of indices.
     */
    public function getIndices(): array
    {
        return $this->getIndex();
    }

    public function getIndicesSummary(): array
    {
        return $this->getTables();
    }

    /**
     *  Returns the mapping details about your indices.
     */
    public function getFieldMapping(string $table, string $column, $raw = false): array
    {
        $mapping = $this->connection->getFieldMapping($this->parseIndexName($table), $column);
        if ($raw) {
            return $mapping;
        }
        $mapping = reset($mapping);

        return Sanitizer::flattenFieldMapping($mapping);
    }

    /**
     * Returns the mapping details about your indices.
     */
    public function getFieldsMapping(string $table, $raw = false): array
    {
        return $this->getFieldMapping($table, '*', $raw);
    }

    /**
     * Returns the mapping details about your indices.
     */
    public function getMappings(string|array $table, $raw = false): array
    {
        $index = $this->parseIndexName($table);
        $params = ['index' => Arr::wrap($index)];

        $mappings = $this->connection->elastic()->indices()->getMapping($params)->asArray();
        if ($raw) {
            return $mappings;
        }
        $mappings = reset($mappings);
        $mappings = Arr::get($mappings, 'mappings');

        return Sanitizer::flattenMappingProperties($mappings);
    }

    /**
     * Shows you the currently configured settings for one or more indices
     */
    public function getSettings(string|array $table): array
    {
        $index = $this->parseIndexName($table);
        $params = ['index' => Arr::wrap($index)];

        return $this->connection->elastic()->indices()->getSettings($params)->asArray();
    }

    /**
     * Replaces `hasIndex` method.
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

        return $this->connection->elastic()->reindex($params)->asArray();
    }

    public function indices(): Indices
    {
        return $this->connection->indices();
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

    public function hasField($index, $field): bool
    {
        return $this->hasColumn($index, $field);
    }

    public function hasFields($index, $fields): bool
    {
        return $this->hasColumns($index, $fields);
    }

    // ----------------------------------------------------------------------
    // Protected Methods
    // ----------------------------------------------------------------------

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

    // ----------------------------------------------------------------------
    // Disabled
    // ----------------------------------------------------------------------

    /**
     * @throws LogicException
     */
    public function hasIndex($table, $index, $type = null)
    {
        throw new LogicException('Elasticsearch does not support index types. Please use the `hasTable` method instead.');
    }
}

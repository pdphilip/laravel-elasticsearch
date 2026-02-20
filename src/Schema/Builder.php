<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema;

use Closure;
use Exception;
use Illuminate\Database\Schema\Builder as BaseBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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
            $this->connection->catIndices([
                'index' => $this->connection->getTablePrefix().'*',
                'format' => 'json',
            ])
        );
    }

    public function getTable($name): array
    {
        return $this->connection->getPostProcessor()->processTables(
            $this->connection->catIndices([
                'index' => $this->connection->getTablePrefix().$name,
                'format' => 'json',
            ])
        );
    }

    public function hasTable($table): bool
    {
        $index = $this->parseIndexName($table);

        return $this->connection->indexExists($index);
    }

    public function getColumns($table): array
    {
        $index = $this->parseIndexName($table);
        $result = $this->connection->getFieldMapping($index, '*');
        $mappings = $result[$index]['mappings'] ?? [];
        $columns[] = [
            'name' => 'id',
            'type_name' => 'text',
            'type' => 'text',
            'collation' => null,
            'nullable' => false,
            'default' => null,
            'auto_increment' => false,
            'comment' => null,
            'generation' => null,
        ];
        foreach ($mappings as $field => $mapping) {

            if (! Str::startsWith($field, '_')) {

                $mapCollection = collect($mapping)->dot()->toArray();
                $type = $mapCollection['mapping.'.$field.'.types'] ?? 'text';
                $columns[] = [
                    'name' => $field,
                    'type_name' => $type,
                    'type' => $type,
                    'collation' => null,
                    'nullable' => false,
                    'default' => null,
                    'auto_increment' => false,
                    'comment' => null,
                    'generation' => null,
                ];
            }
        }

        return $columns;
    }

    public function getIndexes($table): array
    {
        $index = $this->parseIndexName($table);
        $result = $this->connection->getFieldMapping($index, '*');
        $mappings = $result[$index]['mappings'] ?? [];

        $indexes = [];
        foreach ($mappings as $field => $mapping) {
            if (! Str::startsWith($field, '_')) {
                $indexes[] = [
                    'name' => $field,
                    'columns' => [$field],
                    'type' => 'elasticsearch',
                    'unique' => false,
                    'primary' => $field === '_id',
                ];
            }
        }

        return $indexes;
    }

    public function getForeignKeys($table): array
    {
        return [];
    }

    public function getViews($schema = null): array
    {
        return [];
    }

    public function hasColumn($table, $column): bool
    {
        $index = $this->parseIndexName($table);
        $result = $this->connection->getFieldMapping($index, $column);

        return ! empty($result[$index]['mappings'][$column]);
    }

    public function hasColumns($table, $columns): bool
    {
        $index = $this->parseIndexName($table);
        $result = $this->connection->getFieldMapping($index, implode(',', $columns));

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
        } catch (Exception $e) {
            // Silently ignore — the intent is "drop if it exists, otherwise do nothing"
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
        return $this->connection->getIndex($this->parseIndexName($indices));
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
        $mappings = $this->connection->getMappings($index);
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

        return $this->connection->getIndexSettings($index);
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

        return $this->connection->reindex($params);
    }

    public function indices()
    {
        return $this->connection->indices();
    }

    // ----------------------------------------------------------------------
    // V4 Backwards Compatibility
    // ----------------------------------------------------------------------

    /**
     * @deprecated v5.0.0 Use table() instead.
     */
    public function modify($index, Closure $callback): void
    {
        $this->table($index, $callback);
    }

    /**
     * @deprecated v5.0.0 Use drop() instead.
     */
    public function delete($index): void
    {
        $this->drop($index);
    }

    /**
     * @deprecated v5.0.0 Use dropIfExists() instead.
     */
    public function deleteIfExists($index): void
    {
        $this->dropIfExists($index);
    }

    /**
     * @deprecated v5.0.0 Use table() instead.
     */
    public function setAnalyser($index, Closure $callback): void
    {
        $this->table($index, $callback);
    }

    /**
     * @deprecated v5.0.0 Use hasColumn() instead.
     */
    public function hasField($index, $field): bool
    {
        return $this->hasColumn($index, $field);
    }

    /**
     * @deprecated v5.0.0 Use hasColumns() instead.
     */
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

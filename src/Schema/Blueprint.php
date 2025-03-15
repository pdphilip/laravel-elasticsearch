<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema;

use Closure;
use Exception;
use Illuminate\Database\Schema\Blueprint as BlueprintBase;
use Illuminate\Support\Fluent;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Laravel\Compatibility\Schema\BlueprintCompatibility;
use PDPhilip\Elasticsearch\Schema\Definitions\AnalyzerPropertyDefinition;
use PDPhilip\Elasticsearch\Schema\Definitions\CharFilterPropertyDefinition;
use PDPhilip\Elasticsearch\Schema\Definitions\FilterPropertyDefinition;
use PDPhilip\Elasticsearch\Schema\Definitions\NormalizerPropertyDefinition;
use PDPhilip\Elasticsearch\Schema\Definitions\PropertyDefinition;
use PDPhilip\Elasticsearch\Schema\Definitions\TokenizerPropertyDefinition;
use PDPhilip\Elasticsearch\Schema\Grammars\Grammar;

class Blueprint extends BlueprintBase
{
    use BlueprintCompatibility;
    use ManagesDefaultMigrations;
    use ManagesElasticMigrations;

    public const DYNAMIC = [
        'TRUE' => true,
        'FALSE' => false,
        'RUNTIME' => 'runtime',
    ];

    protected string $alias;

    protected string $document;

    protected array $indexSettings = [];

    protected array $analyzers = [];

    protected array $tokenizers = [];

    protected array $filters = [];

    protected array $charFilters = [];

    protected array $normalizers = [];

    protected array $meta = [];

    /**
     * Add a key-value pair to the index settings.
     *
     * @param  string  $key  The setting name.
     * @param  mixed  $value  The setting value.
     */
    public function withSetting(string $key, mixed $value): void
    {
        $this->indexSettings[$key] = $value;
    }

    public function withMapping(string $key, mixed $value): void
    {
        $this->addMetaField($key, $value);
    }

    /**
     * Set the alias for the Blueprint.
     *
     * @param  string  $alias  The alias to be set.
     */
    public function alias(string $alias): void
    {
        $this->alias = $alias;
    }

    /**
     * @return Closure[]
     */
    public function toDSL(Connection $connection, Grammar $grammar): array
    {
        $this->addImpliedCommands($connection, $grammar);

        $statements = [];

        // Each type of command has a corresponding compiler function on the schema
        // grammar which is used to build the necessary SQL statements to build
        // the blueprint element, so we'll just call that compilers function.
        $this->ensureCommandsAreValid($connection);

        foreach ($this->commands as $command) {
            if (! empty($command->name)) {
                $method = 'compile'.ucfirst((string) $command->name);

                if (method_exists($grammar, $method)) {
                    if (! is_null($statement = $grammar->$method($this, $command, $connection))) {
                        $statements[] = $statement;
                    }
                }
            }
        }

        return $statements;
    }

    public function document(string $name): void
    {
        $this->document = $name;
    }

    /**
     * @throws Exception
     */
    public function dynamic(string|bool $option = self::DYNAMIC['TRUE']): void
    {

        if (in_array($option, self::DYNAMIC)) {
            $this->addMetaField('dynamic', $option);

            return;
        }

        throw new Exception(
            "$option is an invalid dynamic option, valid options are: ".implode(', ', self::DYNAMIC)
        );

    }

    /**
     * Add a key-value pair to the meta data.
     *
     * @param  string  $key  The meta key.
     * @param  mixed  $value  The meta value.
     */
    public function addMetaField(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }

    /**
     * Get the alias for the Blueprint. If no alias is set, return the table name.
     *
     * @return string The alias or table name.
     */
    public function getAlias(): string
    {
        return $this->alias ?? $this->getTable();
    }

    /**
     * Get the index name for the blueprint.
     *
     * @return string The index name.
     */
    public function getIndex(): string
    {
        return $this->getTable();
    }

    /**
     * Get the index settings for the Blueprint.
     *
     * @return array The array of index settings.
     */
    public function getIndexSettings(): array
    {
        return $this->indexSettings;
    }

    /**
     * Get the columns on the blueprint that should be added.
     *
     * @return AnalyzerPropertyDefinition[]
     */
    public function getAddedAnalyzers()
    {
        return array_filter($this->analyzers, function ($column) {
            return ! $column->change;
        });
    }

    /**
     * @return TokenizerPropertyDefinition[]
     */
    public function getAddedTokenizers()
    {
        return array_filter($this->tokenizers, function ($column) {
            return ! $column->change;
        });
    }

    /**
     * @return CharFilterPropertyDefinition[]
     */
    public function getAddedCharFilters()
    {
        return array_filter($this->charFilters, function ($column) {
            return ! $column->change;
        });
    }

    /**
     * @return FilterPropertyDefinition[]
     */
    public function getAddedFilters()
    {
        return array_filter($this->filters, function ($column) {
            return ! $column->change;
        });
    }

    /**
     * @return NormalizerPropertyDefinition[]
     */
    public function getAddedNormalizers()
    {
        return array_filter($this->normalizers, function ($column) {
            return ! $column->change;
        });
    }

    /**
     * Get the metadata for the Blueprint.
     *
     * @return array The array of metadata.
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Set metadata for the Blueprint.
     *
     * @param  array  $meta  An associative array representing metadata.
     */
    public function meta(array $meta): void
    {
        $this->addMetaField('_meta', $meta);
    }

    /**
     * Adds a new property definition to the blueprint.
     *
     * @param  string  $type  The type of the property.
     * @param  string  $name  The name of the property.
     * @param  array  $parameters  Additional parameters for the property.
     * @return PropertyDefinition The created property definition.
     */
    public function property(string $type, string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn($type, $name, $parameters);
    }

    /**
     * Indicate that the table should be created if it doesn't exist.
     */
    public function createIfNotExists(): Fluent
    {
        return $this->addCommand('createIfNotExists');
    }

    /**
     * {@inheritDoc}
     */
    public function addColumn($type, $name, array $parameters = [])
    {
        $attributes = ['name'];

        if (! empty($type)) {
            $attributes[] = 'type';
        }

        $this->columns[] = $column = new PropertyDefinition(
            array_merge(compact(...$attributes), $parameters)
        );

        return $column;
    }

    /**
     * Add a new analyzer to the blueprint.
     */
    public function addAnalyzer(string $name): AnalyzerPropertyDefinition
    {
        $analyzer = new AnalyzerPropertyDefinition(compact('name'));
        $this->analyzers[] = $analyzer;

        return $analyzer;
    }

    /**
     * Add a new tokenizer to the blueprint.
     */
    public function addTokenizer(string $name): TokenizerPropertyDefinition
    {
        $tokenizer = new TokenizerPropertyDefinition(compact('name'));
        $this->tokenizers[] = $tokenizer;

        return $tokenizer;
    }

    /**
     * Add a new filter to the blueprint.
     */
    public function addFilter(string $name): FilterPropertyDefinition
    {
        $filter = new FilterPropertyDefinition(compact('name'));
        $this->filters[] = $filter;

        return $filter;
    }

    /**
     * Add a new char filter to the blueprint.
     */
    public function addCharFilter(string $name): CharFilterPropertyDefinition
    {
        $charFilter = new CharFilterPropertyDefinition(compact('name'));
        $this->charFilters[] = $charFilter;

        return $charFilter;
    }

    /**
     * Add a new normalizer to the blueprint.
     */
    public function addNormalizer(string $name): NormalizerPropertyDefinition
    {
        $normalizer = new NormalizerPropertyDefinition(compact('name'));
        $this->normalizers[] = $normalizer;

        return $normalizer;
    }

    public function routingRequired(): void
    {
        $this->addMetaField('_routing', ['required' => true]);
    }

    public function update(): Fluent
    {
        return $this->addCommand('update');
    }
}

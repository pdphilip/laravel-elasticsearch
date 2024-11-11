<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema;

use Illuminate\Database\Schema\Blueprint as BlueprintBase;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Schema\Definitions\PropertyDefinition;
use PDPhilip\Elasticsearch\Schema\Grammars\Grammar;
use PDPhilip\Elasticsearch\Traits\Schema\ManagesDefaultMigrations;

class Blueprint extends BlueprintBase
{
    use ManagesDefaultMigrations;

    /** @var string */
    protected $alias;

    /** @var string */
    protected $document;

    /** @var array */
    protected $meta = [];

    /** @var array */
    protected $indexSettings = [];

    /**
     * {@inheritDoc}
     */
    public function addColumn($type, $name, array $parameters = [])
    {
        $attributes = ['name'];

        if (isset($type)) {
            $attributes[] = 'type';
        }

        $this->columns[] = $column = new PropertyDefinition(
            array_merge(compact(...$attributes), $parameters)
        );

        return $column;
    }

    public function addIndexSettings(string $key, $value): void
    {
        $this->indexSettings[$key] = $value;
    }

    public function addMetaField(string $key, $value): void
    {
        $this->meta[$key] = $value;
    }

    public function alias(string $alias): void
    {
        $this->alias = $alias;
    }

    /**
     * Execute the blueprint against the database.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @param  \Illuminate\Database\Schema\Grammars\Grammar  $grammar
     * @return void
     */
    public function build($connection, $grammar)
    {
        foreach ($this->toDSL($connection, $grammar) as $statement) {
            if ($connection->pretending()) {
                return;
            }

            $statement($this, $connection);
        }
    }

    /**
     * @param  string  $name
     */
    public function date($name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('date', $name, $parameters);
    }

    public function dateRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('date_range', $name, $parameters);
    }

    public function document(string $name): void
    {
        $this->document = $name;
    }

    public function doubleRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('double_range', $name, $parameters);
    }

    /**
     * @param  bool|string  $value
     */
    public function dynamic($value): void
    {
        $this->addMetaField('dynamic', $value);
    }

    public function enableAll(): void
    {
        $this->addMetaField('_all', ['enabled' => true]);
    }

    public function enableFieldNames(): void
    {
        $this->addMetaField('_field_names', ['enabled' => true]);
    }

    public function floatRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('float_range', $name, $parameters);
    }

    public function geoPoint(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('geo_point', $name, $parameters);
    }

    public function geoShape(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('geo_shape', $name, $parameters);
    }

    public function getAlias(): string
    {
        return $this->alias ?? $this->getTable();
    }

    public function getDocumentType(): string
    {
        return $this->document ?? Str::singular($this->getTable());
    }

    public function getIndex(): string
    {
        return $this->getTable();
    }

    public function getIndexSettings(): array
    {
        return $this->indexSettings;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function integerRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('integer_range', $name, $parameters);
    }

    public function ip(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->ipAddress($name, $parameters);
    }

    public function ipRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('ip_range', $name, $parameters);
    }

    public function join(string $name, array $relations): PropertyDefinition
    {
        return $this->addColumn('join', $name, compact('relations'));
    }

    public function keyword(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('keyword', $name, $parameters);
    }

    public function long(string $name): PropertyDefinition
    {
        return $this->addColumn('long', $name);
    }

    public function longRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('long_range', $name, $parameters);
    }

    public function meta(array $meta): void
    {
        $this->addMetaField('_meta', $meta);
    }

    /**
     * @param  \Closure  $parameters
     */
    public function nested(string $name): PropertyDefinition
    {
        return $this->addColumn('nested', $name);
    }

    /**
     * @param  \Closure  $parameters
     * @return PropertyDefinition|\Illuminate\Database\Schema\ColumnDefinition
     */
    public function object(string $name)
    {
        return $this->addColumn(null, $name);
    }

    public function percolator(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('percolator', $name, $parameters);
    }

    public function range(string $type, string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn($type, $name, $parameters);
    }

    public function property(string $type, string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn($type, $name, $parameters);
    }

    public function routingRequired(): void
    {
        $this->addMetaField('_routing', ['required' => true]);
    }

    /**
     * @param  string  $column
     * @param  bool  $hasKeyword  adds a keyword subfield.
     */
    public function text($column, bool $hasKeyword = false, array $parameters = []): PropertyDefinition
    {
        if (! $hasKeyword) {
            return $this->addColumn('text', $column, $parameters);
        }

        return $this->addColumn('text', $column, $parameters)->fields(function (Blueprint $field) {
            $field->keyword('keyword', ['ignore_above' => 256]);
        });
    }

    /**
     * @return \Closure[]
     */
    public function toDSL(Connection $connection, Grammar $grammar)
    {
        $this->addImpliedCommands($connection, $grammar);

        $statements = [];

        // Each type of command has a corresponding compiler function on the schema
        // grammar which is used to build the necessary SQL statements to build
        // the blueprint element, so we'll just call that compilers function.
        $this->ensureCommandsAreValid($connection);

        foreach ($this->commands as $command) {
            $method = 'compile'.ucfirst($command->name);

            if (method_exists($grammar, $method)) {
                if (! is_null($statement = $grammar->$method($this, $command, $connection))) {
                    $statements[] = $statement;
                }
            }
        }

        return $statements;
    }

    public function tokenCount(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('token_count', $name, $parameters);
    }

    /**
     * @return \Illuminate\Support\Fluent
     */
    public function update()
    {
        return $this->addCommand('update');
    }
}

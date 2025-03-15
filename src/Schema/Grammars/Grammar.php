<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema\Grammars;

use Closure;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Database\Schema\Grammars\Grammar as BaseGrammar;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Laravel\Compatibility\Schema\GrammarCompatibility;
use PDPhilip\Elasticsearch\Schema\Blueprint;

class Grammar extends BaseGrammar
{
    use GrammarCompatibility;

    /** @var array */
    protected $modifiers = [
        'Boost',
        'Dynamic',
        'Fields',
        'Format',
        'IndexField',
        'Properties',
        'NullValue',
        'CopyTo',
        'Analyzer',
        'SearchAnalyzer',
        'SearchQuoteAnalyzer',
        'Coerce',
        'DocValues',
        'Norms',
    ];

    public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection): Closure
    {
        return function () use ($blueprint, $connection): void {
            $body = $this->buildBody($blueprint);
            $connection->createIndex($index = $blueprint->getIndex(), $body);

            $alias = $blueprint->getAlias();
            if ($alias !== $index && ! $connection->elastic()->indices()->existsAlias(['name' => $alias])->asBool()) {
                $connection->createAlias($index, $alias);
            }
        };
    }

    public function compileCreateIfNotExists(Blueprint $blueprint, Fluent $command, Connection $connection): Closure
    {
        return function () use ($connection, $blueprint): void {

            $index = $connection->elastic()->indices()->exists(['index' => $blueprint->getTable()]);
            if (! $index->asBool()) {
                $body = $this->buildBody($blueprint);
                $connection->createIndex($blueprint->getIndex(), $body);
            }
        };
    }

    public function compileUpdate(Blueprint $blueprint, Fluent $command, Connection $connection): Closure
    {
        return function (Blueprint $blueprint, Connection $connection): void {
            $body = $this->buildBody($blueprint);
            $connection->updateIndex($blueprint->getAlias(), $body);
        };
    }

    protected function buildBody(Blueprint $blueprint): array
    {
        $body = [
            'mappings' => array_merge(['properties' => $this->getColumns($blueprint)], $blueprint->getMeta()),
        ];
        if ($settings = $blueprint->getIndexSettings()) {
            $body['settings'] = $settings;
        }

        if ($analysis = $this->getAnalysis($blueprint)) {
            $body['settings']['analysis'] = $analysis;
        }

        return $body;
    }

    /**
     * {@inheritDoc}
     */
    protected function addModifiers($sql, BaseBlueprint $blueprint, Fluent $property)
    {
        foreach ($this->modifiers as $modifier) {
            if (method_exists($this, $method = "modify{$modifier}")) {
                $property = $this->{$method}($blueprint, $property);
            }
        }

        return $property;
    }

    protected function format(Blueprint $blueprint, Fluent $property): Fluent
    {
        if (isset($property->format) && ! is_string($property->format)) {
            throw new InvalidArgumentException('Format modifier must be a string', 400);
        }

        return $property;
    }

    protected function getColumns(BaseBlueprint $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getAddedColumns() as $property) {
            // Pass empty string as we only need to modify the property and return it.
            $column = $this->addModifiers('', $blueprint, $property);
            // @phpstan-ignore-next-line
            $fieldName = Str::snake($column->name);
            // @phpstan-ignore-next-line
            $column->name = $fieldName;
            // @phpstan-ignore-next-line
            $columns[] = $column->toArray();
        }

        return $this->buildPropertiesFromColumns($columns);
    }

    protected function buildPropertiesFromColumns($columns): array
    {
        $properties = [];

        foreach ($columns as $column) {
            $field = $column['name'];
            unset($column['name']);
            if (! empty($properties[$field])) {
                $type = $column['type'];
                foreach ($column as $key => $value) {
                    $properties[$field]['fields'][$type][$key] = $value;
                }

                continue;
            }
            foreach ($column as $key => $value) {
                $properties[$field][$key] = $value;
            }
        }

        return $properties;
    }

    protected function getAnalysis(Blueprint $blueprint): array
    {
        return array_merge(
            $this->getAnalyzers($blueprint),
            $this->getTokenizers($blueprint),
            $this->getFilters($blueprint),
            $this->getCharFilters($blueprint),
            $this->getNormalizers($blueprint),
        );

    }

    protected function getAnalyzers(Blueprint $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getAddedAnalyzers() as $property) {
            $column = $property;
            $key = Str::snake($column->name);
            unset($column->name);

            $columns['analyzer'][$key] = $column->toArray();
        }

        return $columns;
    }

    protected function getTokenizers(Blueprint $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getAddedTokenizers() as $property) {
            $column = $property;
            $key = Str::snake($column->name);
            unset($column->name);

            $columns['tokenizer'][$key] = $column->toArray();
        }

        return $columns;
    }

    protected function getCharFilters(Blueprint $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getAddedCharFilters() as $property) {
            $column = $property;
            $key = Str::snake($column->name);
            unset($column->name);

            $columns['char_filter'][$key] = $column->toArray();
        }

        return $columns;
    }

    protected function getFilters(Blueprint $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getAddedFilters() as $property) {
            $column = $property;
            $key = Str::snake($column->name);
            unset($column->name);

            $columns['filter'][$key] = $column->toArray();
        }

        return $columns;
    }

    protected function getNormalizers(Blueprint $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getAddedNormalizers() as $property) {
            $column = $property;
            $key = Str::snake($column->name);
            unset($column->name);

            $columns['normalizer'][$key] = $column->toArray();
        }

        return $columns;
    }

    // ----------------------------------------------------------------------
    // Modifiers
    // ----------------------------------------------------------------------

    protected function modifyBoost(Blueprint $blueprint, Fluent $property): Fluent
    {
        if (! is_null($property->boost) && ! is_numeric($property->boost)) {
            throw new InvalidArgumentException('Boost modifier must be numeric', 400);
        }

        return $property;
    }

    protected function modifyDynamic(Blueprint $blueprint, Fluent $property): Fluent
    {
        if (! is_null($property->dynamic) && ! is_bool($property->dynamic)) {
            throw new InvalidArgumentException('Dynamic modifier must be a boolean', 400);
        }

        return $property;
    }

    protected function modifyNullValue(Blueprint $blueprint, Fluent $property): Fluent
    {
        if (! empty($property->nullValue) || $property->nullValue === false || $property->nullValue === 0) {

            if ($property->type == 'text') {
                throw new InvalidArgumentException('text fields can\'t have a nullValue', 400);
            }

            $property->null_value = $property->nullValue;
            unset($property->nullValue);
        }

        return $property;
    }

    protected function modifyFields(Blueprint $blueprint, Fluent $property): Fluent
    {
        if (! is_null($property->fields)) {
            $fields = $property->fields;
            $fields && $fields($blueprint = $this->createBlueprint($blueprint));

            $property->fields = $this->getColumns($blueprint);
        }

        return $property;
    }

    protected function modifyProperties(Blueprint $blueprint, Fluent $property): Fluent
    {
        if (! is_null($property->properties)) {
            $properties = $property->properties;
            $properties && $properties($blueprint = $this->createBlueprint($blueprint));

            $property->properties = $this->getColumns($blueprint);
        }

        return $property;
    }

    protected function modifyCopyTo(Blueprint $blueprint, Fluent $property): Fluent
    {
        if (! empty($property->copyTo)) {
            $property->copy_to = $property->copyTo;
            unset($property->copyTo);
        }

        return $property;
    }

    protected function modifyIndexField(Blueprint $blueprint, Fluent $property): Fluent
    {

        if (isset($property->indexField)) {
            $property->index = $property->indexField;
            unset($property->indexField);
        }

        return $property;
    }

    protected function modifyAnalyzer(Blueprint $blueprint, Fluent $property): Fluent
    {
        // Nothing to do here, including for consistency or possible future use
        return $property;
    }

    protected function modifySearchAnalyzer(Blueprint $blueprint, Fluent $property): Fluent
    {
        if (isset($property->searchAnalyzer)) {
            $property->search_analyzer = $property->searchAnalyzer;
            unset($property->searchAnalyzer);
        }

        return $property;
    }

    protected function modifySearchQuoteAnalyzer(Blueprint $blueprint, Fluent $property): Fluent
    {
        if (isset($property->searchQuoteAnalyzer)) {
            $property->search_quote_analyzer = $property->searchQuoteAnalyzer;
            unset($property->searchQuoteAnalyzer);
        }

        return $property;
    }

    protected function modifyCoerce(Blueprint $blueprint, Fluent $property): Fluent
    {
        // Nothing to do here, including for consistency or possible future use
        return $property;
    }

    protected function modifyDocValues(Blueprint $blueprint, Fluent $property): Fluent
    {
        if (isset($property->docValues)) {
            $property->doc_values = $property->docValues;
            unset($property->docValues);
        }

        return $property;
    }

    protected function modifyNorms(Blueprint $blueprint, Fluent $property): Fluent
    {
        // Nothing to do here, including for consistency or possible future use
        return $property;
    }
}

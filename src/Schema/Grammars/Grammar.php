<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema\Grammars;

use Closure;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Database\Schema\Grammars\Grammar as BaseGrammar;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Schema\Blueprint;

class Grammar extends BaseGrammar
{
    /** @var array */
    protected $modifiers = ['Boost', 'Dynamic', 'Fields', 'Format', 'Index', 'Properties', 'NullValue'];

    public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection): Closure
    {
        return function () use ($blueprint, $connection): void {
            $body = [
                'mappings' => array_merge(['properties' => $this->getColumns($blueprint)], $blueprint->getMeta()),
            ];

            if ($settings = $blueprint->getIndexSettings()) {
                $body['settings'] = $settings;
            }

            if ($analyzers = $this->getAnalyzers($blueprint)) {
                $body['settings']['analysis'] = $analyzers;
            }

            $connection->createIndex(
                $index = $blueprint->getIndex(), $body
            );

            $alias = $blueprint->getAlias();
            if ($alias !== $index && ! $connection->indices()->existsAlias(['name' => $alias])->asBool()) {
                $connection->createAlias($index, $alias);
            }
        };
    }

    public function compileDrop(Blueprint $blueprint, Fluent $command, Connection $connection): Closure
    {
        return function (Blueprint $blueprint, Connection $connection): void {
            $connection->dropIndex(
              $blueprint->getTable()
            );
        };
    }

    public function compileDropIfExists(Blueprint $blueprint, Fluent $command, Connection $connection): Closure
    {
        return function () use ($connection, $blueprint): void {
            $index = collect($connection->cat()->indices(['format' => 'JSON'])->asArray())
                ->firstWhere(function ($index) use ($blueprint) {
                    return Str::contains($index['index'], $blueprint->getTable());
                });

            if ($index) {
                $connection->dropIndex($index['index']);
            }
        };
    }

    public function compileCreateIfNotExists(Blueprint $blueprint, Fluent $command, Connection $connection): Closure
    {
        return function () use ($connection, $blueprint, $command): void {

            $index = $connection->indices()->exists(['index' => $blueprint->getTable()]);
            if (!$index->asBool()) {
              $connection->createIndex(
                $blueprint->getIndex(), []
              );
            }
        };
    }

    public function compileUpdate(Blueprint $blueprint, Fluent $command, Connection $connection): Closure
    {
        return function (Blueprint $blueprint, Connection $connection): void {
            $connection->updateIndex(
                $blueprint->getAlias(),
                array_merge(
                    ['properties' => $this->getColumns($blueprint)],
                    $blueprint->getMeta(),
                )
            );
        };
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

    /**
     * @return Fluent
     */
    protected function format(Blueprint $blueprint, Fluent $property)
    {
        if (! is_string($property->format)) {
            throw new \InvalidArgumentException('Format modifier must be a string', 400);
        }

        return $property;
    }

    /**
     * @return array
     */
    protected function getColumns(BaseBlueprint $blueprint)
    {
        $columns = [];

        foreach ($blueprint->getAddedColumns() as $property) {
            // Pass empty string as we only need to modify the property and return it.
            $column = $this->addModifiers('', $blueprint, $property);
            $key = Str::snake($column->name);
            unset($column->name);

            $columns[$key] = $column->toArray();
        }

        return $columns;
    }

    /**
     * @return array
     */
    protected function getAnalyzers(BaseBlueprint $blueprint)
    {
        $columns = [];

        foreach ($blueprint->getAddedAnalyzers() as $property) {
            $column = $property;
            $key = Str::snake($column->name);
            unset($column->name);

            $columns[$key] = $column->toArray();
        }

        return $columns;
    }

    /**
     * @return Fluent
     */
    protected function modifyBoost(Blueprint $blueprint, Fluent $property)
    {
        if (! is_null($property->boost) && ! is_numeric($property->boost)) {
            throw new \InvalidArgumentException('Boost modifier must be numeric', 400);
        }

        return $property;
    }

    /**
     * @return Fluent
     */
    protected function modifyDynamic(Blueprint $blueprint, Fluent $property)
    {
        if (! is_null($property->dynamic) && ! is_bool($property->dynamic)) {
            throw new \InvalidArgumentException('Dynamic modifier must be a boolean', 400);
        }

        return $property;
    }

    /**
     * @return Fluent
     */
    protected function modifyNullValue(Blueprint $blueprint, Fluent $property)
    {

      if (! empty($property->nullValue) || $property->nullValue === false || $property->nullValue === 0) {

        if($property->type == "text"){
          throw new \InvalidArgumentException('text feilds can\'t have a nullValue', 400);
        }

        $property->null_value = $property->nullValue;
        unset($property->nullValue);
      }

      return $property;
    }

    /**
     * @return Fluent
     */
    protected function modifyFields(Blueprint $blueprint, Fluent $property)
    {
        if (! is_null($property->fields)) {
            $fields = $property->fields;
            $fields && $fields($blueprint = $this->createBlueprint());

            $property->fields = $this->getColumns($blueprint);
        }

        return $property;
    }

    /**
     * @return Fluent
     */
    protected function modifyProperties(Blueprint $blueprint, Fluent $property)
    {
        if (! is_null($property->properties)) {
            $properties = $property->properties;
            $properties && $properties($blueprint = $this->createBlueprint());

            $property->properties = $this->getColumns($blueprint);
        }

        return $property;
    }

    private function createBlueprint(): Blueprint
    {
        return new Blueprint('');
    }
}

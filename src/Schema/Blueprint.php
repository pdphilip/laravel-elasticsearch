<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema;

use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Connection;
use \Illuminate\Database\Schema\Blueprint as BlueprintBase;
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
   * @inheritDoc
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

  /**
   * @param string $key
   * @param array  $value
   */
  public function addIndexSettings(string $key, array $value): void
  {
    $this->indexSettings[$key] = $value;
  }

  /**
   * @param string $key
   * @param        $value
   */
  public function addMetaField(string $key, $value): void
  {
    $this->meta[$key] = $value;
  }

  /**
   * @param string $alias
   */
  public function alias(string $alias): void
  {
    $this->alias = $alias;
  }

  /**
   * Execute the blueprint against the database.
   *
   * @param \Illuminate\Database\Connection              $connection
   * @param \Illuminate\Database\Schema\Grammars\Grammar $grammar
   *
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
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function date($name, array $parameters = []): PropertyDefinition
  {
    return $this->addColumn('date', $name, $parameters);
  }

  /**
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function dateRange(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->range('date_range', $name, $parameters);
  }

  /**
   * @param string $name
   */
  public function document(string $name): void
  {
    $this->document = $name;
  }

  /**
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function doubleRange(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->range('double_range', $name, $parameters);
  }

  /**
   * @param bool|string $value
   */
  public function dynamic($value): void
  {
    $this->addMetaField('dynamic', $value);
  }

  /**
   * @return void
   */
  public function enableAll(): void
  {
    $this->addMetaField('_all', ['enabled' => true]);
  }

  /**
   * @return void
   */
  public function enableFieldNames(): void
  {
    $this->addMetaField('_field_names', ['enabled' => true]);
  }

  /**
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function floatRange(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->range('float_range', $name, $parameters);
  }

  /**
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function geoPoint(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->addColumn('geo_point', $name, $parameters);
  }

  /**
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function geoShape(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->addColumn('geo_shape', $name, $parameters);
  }

  /**
   * @return string
   */
  public function getAlias(): string
  {
    return ($this->alias ?? $this->getTable());
  }

  /**
   * @return string
   */
  public function getDocumentType(): string
  {
    return $this->document ?? Str::singular($this->getTable());
  }

  /**
   * @return string
   */
  public function getIndex(): string
  {
    return $this->getTable();
  }

  /**
   * @return array
   */
  public function getIndexSettings(): array
  {
    return $this->indexSettings;
  }

  /**
   * @return array
   */
  public function getMeta(): array
  {
    return $this->meta;
  }

  /**
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function integerRange(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->range('integer_range', $name, $parameters);
  }

  /**
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function ip(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->ipAddress($name, $parameters);
  }

  /**
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function ipRange(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->range('ip_range', $name, $parameters);
  }

  /**
   * @param string $name
   * @param array  $relations
   *
   * @return PropertyDefinition
   */
  public function join(string $name, array $relations): PropertyDefinition
  {
    return $this->addColumn('join', $name, compact('relations'));
  }

  /**
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function keyword(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->addColumn('keyword', $name, $parameters);
  }

  /**
   * @param string $name
   *
   * @return PropertyDefinition
   */
  public function long(string $name):PropertyDefinition
  {
    return $this->addColumn('long', $name);
  }

  /**
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function longRange(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->range('long_range', $name, $parameters);
  }

  /**
   * @param array $meta
   */
  public function meta(array $meta): void
  {
    $this->addMetaField('_meta', $meta);
  }

  /**
   * @param string   $name
   * @param \Closure $parameters
   *
   * @return PropertyDefinition
   */
  public function nested(string $name): PropertyDefinition
  {
    return $this->addColumn('nested', $name);
  }

  /**
   * @param string   $name
   * @param \Closure $parameters
   *
   * @return PropertyDefinition|\Illuminate\Database\Schema\ColumnDefinition
   */
  public function object(string $name)
  {
    return $this->addColumn(null, $name);
  }

  /**
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function percolator(string $name, array $parameters = []): PropertyDefinition
  {
    return $this->addColumn('percolator', $name, $parameters);
  }

  /**
   * @param string $type
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
  public function range(string $type, string $name, array $parameters = []): PropertyDefinition
  {
    return $this->addColumn($type, $name, $parameters);
  }

  /**
   * @return void
   */
  public function routingRequired(): void
  {
    $this->addMetaField('_routing', ['required' => true]);
  }

  /**
   * @param string     $column
   * @param array $parameters
   * @param boolean $hasKeyword adds a keyword subfield.
   *
   * @return PropertyDefinition
   */
  public function text($column,  bool $hasKeyword = false, array $parameters = []): PropertyDefinition
  {
    if(!$hasKeyword) {
      return $this->addColumn('text', $column, $parameters);
    }

    return $this->addColumn('text', $column, $parameters)->fields(function (Blueprint $field) {
      $field->keyword('keyword', ['ignore_above' => 256]);
    });
  }

  /**
   * @param Connection $connection
   * @param Grammar    $grammar
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
      $method = 'compile' . ucfirst($command->name);

      if (method_exists($grammar, $method)) {
        if (!is_null($statement = $grammar->$method($this, $command, $connection))) {
          $statements[] = $statement;
        }
      }
    }

    return $statements;
  }

  /**
   * @param string $name
   * @param array  $parameters
   *
   * @return PropertyDefinition
   */
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

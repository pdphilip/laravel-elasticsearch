<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema;

use PDPhilip\Elasticsearch\Connection;
use \Illuminate\Database\Schema\Blueprint as BlueprintBase;
use PDPhilip\Elasticsearch\Schema\Grammars\Grammar;

class Blueprint extends BlueprintBase
{

    /** @var string */
    protected $alias;

    /** @var string */
    protected $document;

    /** @var array */
    protected $meta = [];

    /** @var array */
    protected $indexSettings = [];


    public function build(Connection|\Illuminate\Database\Connection $connection, Grammar|\Illuminate\Database\Schema\Grammars\Grammar $grammar)
    {
      foreach ($this->toDSL($connection, $grammar) as $statement) {
        $connection->statement($statement);
      }
    }

  /**
   * @return string
   */
  public function getIndex(): string
  {
    return $this->getTable();
  }

  /**
   * @return string
   */
  public function getAlias(): string
  {
    return ($this->alias ?? $this->getTable());
  }

  public function toDSL(Connection $connection, Grammar $grammar): array
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

    //----------------------------------------------------------------------
    // Index blueprints
    //----------------------------------------------------------------------

    public function text($field): Definitions\FieldDefinition
    {
        return $this->addField('text', $field);
    }

    protected function addField($type, $field, array $parameters = [])
    {
        return $this->addFieldDefinition(new Definitions\FieldDefinition(
            array_merge(compact('type', 'field'), $parameters)
        ));
    }

    protected function addFieldDefinition($definition)
    {
        $this->parameters['properties'][] = $definition;

        return $definition;
    }

    public function array($field): Definitions\FieldDefinition
    {
        return $this->addField('text', $field);
    }

    //----------------------------------------------------------------------
    // Numeric Types
    //----------------------------------------------------------------------

    public function boolean($field): Definitions\FieldDefinition
    {
        return $this->addField('boolean', $field);
    }

    public function keyword($field): Definitions\FieldDefinition
    {
        return $this->addField('keyword', $field);
    }

    public function long($field): Definitions\FieldDefinition
    {
        return $this->addField('long', $field);
    }

    public function integer($field, $autoIncrement = false, $unsigned = false): Definitions\FieldDefinition
    {
        return $this->addField('integer', $field);
    }

    public function short($field): Definitions\FieldDefinition
    {
        return $this->addField('short', $field);
    }

    public function byte($field): Definitions\FieldDefinition
    {
        return $this->addField('byte', $field);
    }

    public function double($field): Definitions\FieldDefinition
    {
        return $this->addField('double', $field);
    }

    public function float($field, $precision = 53): Definitions\FieldDefinition
    {
        return $this->addField('float', $field);
    }

    public function halfFloat($field): Definitions\FieldDefinition
    {
        return $this->addField('half_float', $field);
    }

    //----------------------------------------------------------------------

    public function scaledFloat($field, $scalingFactor = 100): Definitions\FieldDefinition
    {
        return $this->addField('scaled_float', $field, [
            'scaling_factor' => $scalingFactor,
        ]);
    }

    public function unsignedLong($field): Definitions\FieldDefinition
    {
        return $this->addField('unsigned_long', $field);
    }

    public function date($field, $format = null): Definitions\FieldDefinition
    {
        if ($format) {
            return $this->addField('date', $field, ['format' => $format]);
        }

        return $this->addField('date', $field);

    }

    public function geo($field): Definitions\FieldDefinition
    {
        return $this->addField('geo_point', $field);
    }

    public function nested($field, $params = []): Definitions\FieldDefinition
    {
        return $this->addField('nested', $field, $params);
    }

    public function alias($field, $path): Definitions\FieldDefinition
    {
        return $this->addField('alias', $field, ['path' => $path]);
    }

    public function ip($field): Definitions\FieldDefinition
    {
        return $this->addField('ip', $field);
    }

    public function mapProperty($field, $type): Definitions\FieldDefinition
    {
        return $this->addField($type, $field);
    }

    public function settings($key, $value): void
    {
        $this->parameters['settings'][$key] = $value;
    }

    //----------------------------------------------------------------------
    // Definitions
    //----------------------------------------------------------------------

    public function map($key, $value): void
    {
        $this->parameters['map'][$key] = $value;
    }

    public function field($type, $field, array $parameters = [])
    {
        return $this->addField($type, $field, $parameters);
    }

    //======================================================================
    // Builders
    //======================================================================

    public function buildIndexCreate(Connection $connection): void
    {
        $connection->setIndex($this->index);
        if ($this->parameters) {
            $this->toDSL();
            $connection->indexCreate($this->parameters);
        }
    }

    //    public function buildReIndex(Connection $connection): void
    //    {
    //        return $connection->reIndex($this->index, $this->newIndex);
    //    }

    //----------------------------------------------------------------------
    // Internal Laravel init migration catchers
    // *Case for when ES is the only datasource
    //----------------------------------------------------------------------

    public function buildIndexModify(Connection $connection): void
    {
        $connection->setIndex($this->index);
        if ($this->parameters) {
            $this->_formatParams();
            $connection->indexModify($this->parameters);
        }
    }

    public function increments($column): Definitions\FieldDefinition
    {
        return $this->addField('keyword', $column);
    }

    //----------------------------------------------------------------------
    // Helpers
    //----------------------------------------------------------------------

    public function string($column, $length = NULL): Definitions\FieldDefinition
    {
        return $this->addField('keyword', $column);
    }

    /**
     * @return \Illuminate\Support\Fluent
     */
    public function update()
    {
      return $this->addCommand('update');
    }

}

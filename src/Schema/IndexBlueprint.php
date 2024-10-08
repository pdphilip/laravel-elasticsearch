<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Schema;

use Illuminate\Support\Fluent;
use PDPhilip\Elasticsearch\Connection;

class IndexBlueprint
{
    /**
     * The Connection object for this blueprint.
     */
    protected Connection $connection;

    protected string $index = '';

    protected ?string $newIndex;

    protected array $parameters = [];

    public function __construct($index, $newIndex = null)
    {
        $this->index = $index;
        $this->newIndex = $newIndex;
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

    public function integer($field): Definitions\FieldDefinition
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

    public function float($field): Definitions\FieldDefinition
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
            $this->_formatParams();
            $connection->indexCreate($this->parameters);
        }
    }

    private function _formatParams(): void
    {
        if ($this->parameters) {
            if (! empty($this->parameters['properties'])) {
                $properties = [];
                foreach ($this->parameters['properties'] as $property) {
                    if ($property instanceof Fluent) {
                        $properties[] = $property->toArray();
                    } else {
                        $properties[] = $property;
                    }
                }
                $this->parameters['properties'] = $properties;
            }
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

    public function string($column): Definitions\FieldDefinition
    {
        return $this->addField('keyword', $column);
    }
}

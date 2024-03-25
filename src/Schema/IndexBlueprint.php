<?php

namespace PDPhilip\Elasticsearch\Schema;

use Illuminate\Database\Schema\ColumnDefinition;
use PDPhilip\Elasticsearch\Connection;
use Closure;
use Illuminate\Support\Fluent;
use RuntimeException;

class IndexBlueprint
{
    /**
     * The Connection object for this blueprint.
     *
     * @var Connection
     */
    protected $connection;
    
    protected $index;
    
    protected $newIndex;
    
    protected $parameters = [];
    
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
    
    public function array($field): Definitions\FieldDefinition
    {
        return $this->addField('text', $field);
    }
    
    public function boolean($field): Definitions\FieldDefinition
    {
        return $this->addField('boolean', $field);
    }
    
    public function keyword($field): Definitions\FieldDefinition
    {
        return $this->addField('keyword', $field);
    }
    
    public function integer($field): Definitions\FieldDefinition
    {
        return $this->addField('integer', $field);
    }
    
    public function long($field): Definitions\FieldDefinition
    {
        return $this->addField('long', $field);
    }
    
    public function float($field): Definitions\FieldDefinition
    {
        return $this->addField('float', $field);
    }
    
    public function short($field): Definitions\FieldDefinition
    {
        return $this->addField('short', $field);
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
    
    public function map($key, $value): void
    {
        $this->parameters['map'][$key] = $value;
    }
    
    public function field($type, $field, array $parameters = [])
    {
        return $this->addField($type, $field, $parameters);
    }
    
    //----------------------------------------------------------------------
    // Definitions
    //----------------------------------------------------------------------
    
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
    
    //======================================================================
    // Builders
    //======================================================================
    
    
    public function buildIndexCreate(Connection $connection)
    {
        $connection->setIndex($this->index);
        if ($this->parameters) {
            $this->_formatParams();
            $connection->indexCreate($this->parameters);
        }
    }
    
    public function buildReIndex(Connection $connection)
    {
        return $connection->reIndex($this->index, $this->newIndex);
    }
    
    public function buildIndexModify(Connection $connection)
    {
        $connection->setIndex($this->index);
        if ($this->parameters) {
            $this->_formatParams();
            $connection->indexModify($this->parameters);
        }
    }
    
    
    //----------------------------------------------------------------------
    // Internal Laravel init migration catchers
    // *Case for when ES is the only datasource
    //----------------------------------------------------------------------
    
    public function increments($column)
    {
        return $this->addField('keyword', $column);
    }
    
    public function string($column)
    {
        return $this->addField('keyword', $column);
    }
    
    
    
    //----------------------------------------------------------------------
    // Helpers
    //----------------------------------------------------------------------
    private function _formatParams()
    {
        if ($this->parameters) {
            if (!empty($this->parameters['properties'])) {
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
}

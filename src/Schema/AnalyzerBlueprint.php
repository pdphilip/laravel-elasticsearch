<?php

namespace PDPhilip\Elasticsearch\Schema;

use PDPhilip\Elasticsearch\Connection;
use Illuminate\Support\Fluent;

class AnalyzerBlueprint
{
    /**
     * The Connection object for this blueprint.
     *
     * @var Connection
     */
    protected $connection;

    protected $index;

    protected $parameters = [];

    public function __construct($index)
    {
        $this->index = $index;
    }

    //----------------------------------------------------------------------
    // Index blueprints
    //----------------------------------------------------------------------

    public function analyzer($name): Definitions\AnalyzerPropertyDefinition
    {
        return $this->addProperty('analyzer', $name);
    }

    public function tokenizer($type): Definitions\AnalyzerPropertyDefinition
    {
        return $this->addProperty('tokenizer', $type);
    }

    public function charFilter($type): Definitions\AnalyzerPropertyDefinition
    {
        return $this->addProperty('char_filter', $type);
    }

    public function filter($type): Definitions\AnalyzerPropertyDefinition
    {
        return $this->addProperty('filter', $type);
    }


    //----------------------------------------------------------------------
    // Definitions
    //----------------------------------------------------------------------

    protected function addProperty($config, $name, array $parameters = [])
    {
        return $this->addPropertyDefinition(new Definitions\AnalyzerPropertyDefinition(
            array_merge(compact('config', 'name'), $parameters)
        ));
    }

    protected function addPropertyDefinition($definition)
    {
        $this->parameters['analysis'][] = $definition;

        return $definition;
    }

    //----------------------------------------------------------------------
    // Builders
    //----------------------------------------------------------------------

    public function buildIndexAnalyzerSettings(Connection $connection)
    {
        $connection->setIndex($this->index);
        if ($this->parameters) {
            $this->_formatParams();
            $connection->indexAnalyzerSettings($this->parameters);
        }
    }

    //----------------------------------------------------------------------
    // Helpers
    //----------------------------------------------------------------------
    private function _formatParams()
    {
        if ($this->parameters) {
            if (!empty($this->parameters['analysis'])) {
                $properties = [];
                foreach ($this->parameters['analysis'] as $property) {
                    if ($property instanceof Fluent) {
                        $properties[] = $property->toArray();
                    } else {
                        $properties[] = $property;
                    }
                }
                $this->parameters['analysis'] = $properties;
            }
        }
    }

}

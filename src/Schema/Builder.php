<?php

namespace PDPhilip\Elasticsearch\Schema;

use Exception;
use Closure;
use PDPhilip\Elasticsearch\Connection;

class Builder
{

    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    //----------------------------------------------------------------------
    //  View Index Meta
    //----------------------------------------------------------------------

    public function getIndices($includeSystem = false)
    {
        return $this->connection->getIndices($includeSystem);
    }

    public function getMappings($index, $forceIndexName = false)
    {
        if (!$forceIndexName) {
            $index = $this->connection->setIndex($index);
        }

        return $this->connection->indexMappings($index);
    }

    public function getSettings($index, $forceIndexName = false)
    {
        if (!$forceIndexName) {
            $index = $this->connection->setIndex($index);
        }

        return $this->connection->indexSettings($index);
    }

    //----------------------------------------------------------------------
    //  Create Index
    //----------------------------------------------------------------------
    public function create($index, Closure $callback)
    {
        $this->builder('buildIndexCreate', tap(new IndexBlueprint($index), function ($blueprint) use ($callback) {
            $callback($blueprint);
        }));
    }

    public function createIfNotExists($index, Closure $callback)
    {
        if ($this->hasIndex($index)) {
            return true;
        }
        $this->builder('buildIndexCreate', tap(new IndexBlueprint($index), function ($blueprint) use ($callback) {
            $callback($blueprint);
        }));
    }

    //----------------------------------------------------------------------
    // Reindex
    //----------------------------------------------------------------------

    public function reIndex($from, $to)
    {
        return $this->connection->reIndex($from, $to);
    }


    //----------------------------------------------------------------------
    // Modify Index
    //----------------------------------------------------------------------

    public function modify($index, Closure $callback)
    {
        $this->builder('buildIndexModify', tap(new IndexBlueprint($index), function ($blueprint) use ($callback) {
            $callback($blueprint);
        }));
    }

    //----------------------------------------------------------------------
    // Delete Index
    //----------------------------------------------------------------------

    public function delete($index)
    {
        $this->connection->setIndex($index);

        return $this->connection->indexDelete();
    }

    public function deleteIfExists($index)
    {
        $this->connection->setIndex($index);
        try {
            return $this->connection->indexDelete($index);
        } catch (Exception $e) {
            return false;
        }
    }

    //----------------------------------------------------------------------
    // Index template
    //----------------------------------------------------------------------
    public function createTemplate($name, Closure $callback)
    {

    }


    //----------------------------------------------------------------------
    // Analysers
    //----------------------------------------------------------------------

    public function setAnalyser($index, Closure $callback)
    {
        $this->analyzerBuilder('buildIndexAnalyzerSettings', tap(new AnalyzerBlueprint($index), function ($blueprint) use ($callback) {
            $callback($blueprint);
        }));
    }



    //----------------------------------------------------------------------
    // Index ops
    //----------------------------------------------------------------------

    public function hasField($index, $field)
    {
        $mappings = $this->getMappings($index);
        $data = $mappings->data;
        try {
            $props = $data[$index]['mappings']['properties'];
            $props = $this->_flattenFields($props);
            $fileList = $this->_sanitizeFlatFields($props);

            if (in_array($field, $fileList)) {
                return true;
            }
        } catch (Exception $e) {

        }

        return false;

    }

    public function hasFields($index, array $fields)
    {
        $mappings = $this->getMappings($index);
        $data = $mappings->data;
        try {
            $props = $data[$index]['mappings']['properties'];
            $props = $this->_flattenFields($props);
            $fileList = $this->_sanitizeFlatFields($props);
            $allFound = true;
            foreach ($fields as $field) {
                if (!in_array($field, $fileList)) {
                    $allFound = false;
                }
            }

            return $allFound;
        } catch (Exception $e) {
            return false;
        }

    }

    public function hasIndex($index)
    {
        return $this->connection->indexExists($index);
    }


    //----------------------------------------------------------------------
    // Manual
    //----------------------------------------------------------------------

    public function dsl($method, $params)
    {
        return $this->connection->indicesDsl($method, $params);
    }

    //----------------------------------------------------------------------
    // Helpers
    //----------------------------------------------------------------------
    function flatten($array, $prefix = '')
    {
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = $result + flatten($value, $prefix.$key.'.');
            } else {
                $result[$prefix.$key] = $value;
            }
        }

        return $result;
    }

    private function _flattenFields($array, $prefix = '')
    {

        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = $result + $this->_flattenFields($value, $prefix.$key.'.');
            } else {
                $result[$prefix.$key] = $value;
            }
        }

        return $result;
    }

    private function _sanitizeFlatFields($flatFields)
    {
        $fields = [];
        if ($flatFields) {
            foreach ($flatFields as $flatField => $value) {
                $parts = explode('.', $flatField);
                $field = $parts[0];
                array_walk($parts, function ($v, $k) use (&$field, $parts) {
                    if ($v == 'properties') {
                        $field .= '.'.$parts[$k + 1];

                    }
                });
                $fields[] = $field;
            }
        }

        return $fields;
    }


    //----------------------------------------------------------------------
    // Builders
    //----------------------------------------------------------------------
    protected function builder($builder, IndexBlueprint $blueprint)
    {
        $blueprint->{$builder}($this->connection);
    }

    protected function analyzerBuilder($builder, AnalyzerBlueprint $blueprint)
    {
        $blueprint->{$builder}($this->connection);
    }
}

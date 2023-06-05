<?php

namespace PDPhilip\Elasticsearch\DSL;

class ParameterBuilder
{
    public static function matchAll()
    {
        return [
            'query' => [
                'match_all' => new \stdClass(),
            ],
        ];
    }
    
    
    public static function queryStringQuery($string)
    {
        return [
            'query' => [
                'query_string' => [
                    'query' => $string,
                ],
            ],
        ];
    }
    
    
    public static function fieldSort($field, $order = 'asc')
    {
        return [
            $field => [
                'order' => $order,
            ],
        ];
    }
    
    public static function maxAggregation($field)
    {
        return [
            'max' => [
                'field' => $field,
            ],
        ];
    }
    
    public static function minAggregation($field)
    {
        return [
            'min' => [
                'field' => $field,
            ],
        ];
    }
    
    public static function avgAggregation($field)
    {
        return [
            'avg' => [
                'field' => $field,
            ],
        ];
    }
    
    public static function sumAggregation($field)
    {
        return [
            'sum' => [
                'field' => $field,
            ],
        ];
    }
    
    public static function matrixAggregation($fields)
    {
        return [
            'matrix_stats' => [
                'fields' => $fields,
            ],
        ];
    }
    
    
}
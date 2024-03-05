<?php

namespace PDPhilip\Elasticsearch\DSL;

use Exception;

trait QueryBuilder
{
    
    protected static $filter;
    
    protected static array $bucketOperators = ['and', 'or'];
    
    protected static array $equivalenceOperators = ['in', 'nin'];
    
    protected static array $clauseOperators = ['ne', 'gt', 'gte', 'lt', 'lte', 'between', 'not_between', 'like', 'not_like', 'exists', 'regex'];
    
    
    //======================================================================
    // Parameter builders
    //======================================================================
    
    /**
     * @throws Exception
     */
    public function buildSearchParams($index, $searchQuery, $searchOptions, $wheres = [], $options = [], $fields = [], $columns = []): array
    {
        $params = [];
        if ($index) {
            $params['index'] = $index;
        }
        $params['body'] = [];
        $queryString['query'] = $searchQuery;
        if ($fields) {
            $queryString['fields'] = [];
            foreach ($fields as $field => $boostLevel) {
                if ($boostLevel > 1) {
                    $field = $field.'^'.$boostLevel;
                }
                $queryString['fields'][] = $field;
            }
            if (count($queryString['fields']) > 1) {
                $queryString['type'] = 'cross_fields';
            }
            
        }
        if ($searchOptions) {
            foreach ($searchOptions as $searchOption => $searchOptionValue) {
                $queryString[$searchOption] = $searchOptionValue;
            }
        }
        
        $wheres = $this->addSearchToWheres($wheres, $queryString);
        $dsl = $this->_buildQuery($wheres);
        
        $params['body']['query'] = $dsl['query'];
        
        if ($columns && $columns != ['*']) {
            $params['body']['_source'] = $columns;
        }
        if ($options) {
            $opts = $this->_buildOptions($options);
            if ($opts) {
                foreach ($opts as $key => $value) {
                    if (isset($params[$key])) {
                        $params[$key] = array_merge($params[$key], $opts[$key]);
                    } else {
                        $params[$key] = $value;
                    }
                }
            }
        }
        if (self::$filter) {
            $params = $this->_parseFilterParameter($params, self::$filter);
            self::$filter = [];
        }
        
        return $params;
    }
    
    /**
     * @throws Exception
     */
    public function buildParams($index, $wheres, $options = [], $columns = [], $_id = null): array
    {
        if ($index) {
            $params = [
                'index' => $index,
            ];
        }
        
        if ($_id) {
            $params['id'] = $_id;
        }
        
        $params['body'] = $this->_buildQuery($wheres);
        if ($columns && $columns != '*') {
            $params['body']['_source'] = $columns;
        }
        $opts = $this->_buildOptions($options);
        if ($opts) {
            foreach ($opts as $key => $value) {
                if (isset($params[$key])) {
                    $params[$key] = array_merge($params[$key], $opts[$key]);
                } else {
                    $params[$key] = $value;
                }
            }
        }
        if (self::$filter) {
            $params = $this->_parseFilterParameter($params, self::$filter);
            self::$filter = [];
        }
        
        return $params;
    }
    
    
    public function createNestedAggs($columns, $sort)
    {
        $aggs = [];
        $terms = [
            'terms' => [
                'field' => $columns[0],
                'size'  => 10000,
            ],
        ];
        if (isset($sort['_count'])) {
            if (!isset($terms['terms']['order'])) {
                $terms['terms']['order'] = [];
            }
            if ($sort['_count'] == 1) {
                $terms['terms']['order'][] = ['_count' => 'asc'];
            } else {
                $terms['terms']['order'][] = ['_count' => 'desc'];
            }
        }
        if (isset($sort[$columns[0]])) {
            if ($sort[$columns[0]] == 1) {
                $terms['terms']['order'][] = ['_key' => 'asc'];
            } else {
                $terms['terms']['order'][] = ['_key' => 'desc'];
            }
        }
        $aggs['by_'.$columns[0]] = $terms;
        if (count($columns) > 1) {
            $aggs['by_'.$columns[0]]['aggs'] = $this->createNestedAggs(array_slice($columns, 1), $sort);
        }
        
        return $aggs;
    }
    
    
    public function addSearchToWheres($wheres, $queryString)
    {
        $clause = ['_' => ['search' => $queryString]];
        if (!$wheres) {
            return $clause;
        }
        if (!empty($wheres['and'])) {
            $wheres['and'][] = $clause;
            
            return $wheres;
        }
        if (!empty($wheres['or'])) {
            $newOrs = [];
            foreach ($wheres['or'] as $cond) {
                $cond['and'][] = $clause;
                $newOrs[] = $cond;
            }
            $wheres['or'] = $newOrs;
            
            return $wheres;
        }
        
        return ['and' => [$wheres, $clause]];
    }
    
    
    //----------------------------------------------------------------------
    // Parsers
    //----------------------------------------------------------------------
    
    public function _escape($value): string
    {
        $specialChars = ['"', '\\', '~', '^', '/'];
        foreach ($specialChars as $char) {
            $value = str_replace($char, "\\".$char, $value);
        }
        if (str_starts_with($value, '-')) {
            $value = '\\'.$value;
        }
        
        return $value;
    }
    
    private function _buildQuery($wheres): array
    {
        if (!$wheres) {
            return ParameterBuilder::matchAll();
        }
        $dsl = $this->_convertWheresToDSL($wheres);
        
        return ParameterBuilder::query($dsl);
    }
    
    
    public function _convertWheresToDSL($wheres): array
    {
        $dsl = ['bool' => []];
        foreach ($wheres as $logicalOperator => $conditions) {
            switch ($logicalOperator) {
                case 'and':
                    $dsl['bool']['must'] = [];
                    foreach ($conditions as $condition) {
                        $parsedCondition = $this->_parseCondition($condition);
                        if (!empty($parsedCondition)) {
                            $dsl['bool']['must'][] = $parsedCondition;
                        }
                    }
                    break;
                case 'or':
                    $dsl['bool']['should'] = [];
                    foreach ($conditions as $conditionGroup) {
                        $boolClause = ['bool' => ['must' => []]];
                        foreach ($conditionGroup as $subConditions) {
                            foreach ($subConditions as $subCondition) {
                                $parsedCondition = $this->_parseCondition($subCondition);
                                if (!empty($parsedCondition)) {
                                    $boolClause['bool']['must'][] = $parsedCondition;
                                }
                            }
                        }
                        if (!empty($boolClause['bool']['must'])) {
                            $dsl['bool']['should'][] = $boolClause;
                        }
                    }
                    break;
                default:
                    return $this->_parseCondition($wheres);
            }
        }
        
        return $dsl;
    }
    
    private function _parseCondition($condition): array
    {
//        dd($condition);
        $field = key($condition);
        $value = current($condition);
        
        
        if (!is_array($value)) {
            
            return ['match' => [$field => $value]];
        } else {
            $operator = key($value);
            $operand = current($value);
            $queryPart = [];
            
            switch ($operator) {
                case 'lt':
                    $queryPart = ['range' => [$field => ['lt' => $operand]]];
                    break;
                case 'lte':
                    $queryPart = ['range' => [$field => ['lte' => $operand]]];
                    break;
                case 'gt':
                    $queryPart = ['range' => [$field => ['gt' => $operand]]];
                    break;
                case 'gte':
                    $queryPart = ['range' => [$field => ['gte' => $operand]]];
                    break;
                case 'search':
                    $queryPart = ['query_string' => $operand];
                    break;
                case 'like':
                    $queryPart = [
                        'query_string' => [
                            'query' => $field.':*'.$this->_escape($operand).'*',
                        ],
                    ];
                    break;
                case 'not_like':
                    $queryPart = [
                        'query_string' => [
                            'query' => '(NOT '.$field.':*'.self::_escape($operand).'*)',
                        ],
                    ];
                    break;
                case 'regex':
                    $queryPart = ['regexp' => [$field => ['value' => $operand]]];
                    break;
                case 'exists':
                    $queryPart = ['exists' => ['field' => $field]];
                    break;
                case 'not_exists':
                    $queryPart = ['bool' => ['must_not' => [['exists' => ['field' => $field]]]]];
                    break;
                case 'ne':
                    $queryPart = ['bool' => ['must_not' => [['match' => [$field => $operand]]]]];
                    break;
                case 'in':
                    $queryPart = ['terms' => [$field => $operand]];
                    break;
                case 'nin':
                    $queryPart = ['bool' => ['must_not' => ['terms' => [$field => $operand]]]];
                    break;
                case 'between':
                    $queryPart = ['range' => [$field => ['gte' => $operand[0], 'lte' => $operand[1]]]];
                    break;
                case 'not_between':
                    $queryPart = ['bool' => ['must_not' => ['range' => [$field => ['gte' => $operand[0], 'lte' => $operand[1]]]]]];
                    break;
                case 'phrase':
                    $queryPart = ['match_phrase' => [$field => $operand]];
                    break;
                case 'nested':
                    $queryPart = [
                        'nested' => [
                            'path'       => $field,
                            'query'      => $this->_convertWheresToDSL($operand['wheres']),
                            'score_mode' => $operand['score_mode'],
                        ],
                    ];
                    break;
                case 'not_nested':
                    $queryPart = [
                        'bool' => [
                            'must_not' => [
                                [
                                    'nested' => [
                                        'path'       => $field,
                                        'query'      => $this->_convertWheresToDSL($operand['wheres']),
                                        'score_mode' => $operand['score_mode'],
                                    ],
                                ],
                            ],
                        ],
                    
                    ];
                    break;
                default:
                    abort('400', 'Invalid operator ['.$operator.'] provided for condition.');
            }
            
            return $queryPart;
        }
    }
    
    /**
     * @throws Exception
     */
    private function _buildOptions($options): array
    {
        $return = [];
        if ($options) {
            foreach ($options as $key => $value) {
                switch ($key) {
                    case 'limit':
                        $return['size'] = $value;
                        break;
                    case 'sort':
                        if (!isset($return['body']['sort'])) {
                            $return['body']['sort'] = [];
                        }
                        $sortBy = $this->_parseSortOrder($value);
                        $return['body']['sort'][] = $sortBy;
                        break;
                    case 'skip':
                        $return['from'] = $value;
                        break;
                    case 'minScore':
                        $return['body']['min_score'] = $value;
                        break;
                    case 'filters':
                        foreach ($value as $filterType => $filerValues) {
                            $this->_parseFilter($filterType, $filerValues);
                        }
                        break;
                    case 'multiple':
                    case 'searchOptions':
                        //Pass through
                        break;
                    default:
                        throw new Exception('Unexpected option: '.$key);
                }
            }
        }
        
        return $return;
    }
    
    public function _parseFilter($filterType, $filterPayload): void
    {
        switch ($filterType) {
            case 'filterGeoBox':
                self::$filter['filter']['geo_bounding_box'][$filterPayload['field']] = [
                    'top_left'     => $filterPayload['topLeft'],
                    'bottom_right' => $filterPayload['bottomRight'],
                ];
                break;
            case 'filterGeoPoint':
                self::$filter['filter']['geo_distance'] = [
                    'distance'              => $filterPayload['distance'],
                    $filterPayload['field'] => [
                        'lat' => $filterPayload['geoPoint'][0],
                        'lon' => $filterPayload['geoPoint'][1],
                    ],
                
                ];
                break;
        }
    }
    
    private function _parseSortOrder($value): array
    {
        $field = array_key_first($value);
        $direction = $value[$field];
        
        $dir = 'desc';
        if ($direction == 1) {
            $dir = 'asc';
        }
        
        return ParameterBuilder::fieldSort($field, $dir);
    }
    
    public function _parseFilterParameter($params, $filer)
    {
        $body = $params['body'];
        $currentQuery = $body['query'];
        
        $filteredBody = [
            'query' => [
                'bool' => [
                    'must'   => [
                        $currentQuery,
                    ],
                    'filter' => $filer['filter'],
                ],
            ],
        ];
        $params['body']['query'] = $filteredBody['query'];
        
        return $params;
        
    }
}

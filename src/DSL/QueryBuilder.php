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
        if ($wheres) {
            $wheres = $this->_buildQuery($wheres);
            $whereQueryString = $wheres['query']['query_string']['query'] ?? null;
            if ($whereQueryString) {
                $queryString['query'] = '('.$searchQuery.') AND '.$whereQueryString;
            }
        }
        
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
        
        $params['body']['query']['query_string'] = $queryString;
        
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
    
    //----------------------------------------------------------------------
    // Parsers
    //----------------------------------------------------------------------
    
    private function _buildQueryString($wheres): string
    {
        if ($wheres) {
            foreach ($wheres as $key => $value) {
                return $this->_parseParams($key, $value);
            }
        }
        
        return '';
    }
    
    private static function _andQueryString($values): string
    {
        $strings = [];
        foreach ($values as $key => $val) {
            $strings[] = self::_parseParams($key, $val);
        }
        
        return '('.implode(' AND ', $strings).')';
    }
    
    private static function _orQueryString($values): string
    {
        $strings = [];
        foreach ($values as $key => $val) {
            $strings[] = self::_parseParams($key, $val);
        }
        
        return '('.implode(' OR ', $strings).')';
    }
    
    private static function _inQueryString($key, $values): string
    {
        $strings = [];
        foreach ($values as $val) {
            $strings[] = self::_parseParams(null, $val);
        }
        
        return '('.$key.':('.implode(' OR ', $strings).'))';
    }
    
    private static function _ninQueryString($key, $values): string
    {
        $strings = [];
        foreach ($values as $val) {
            $strings[] = self::_parseParams(null, $val);
        }
        
        return '(NOT '.$key.':('.implode(' OR ', $strings).'))';
    }
    
    private static function _parseParams($key, $value): string
    {
        
        if ($key === 'and' || $key === 'or') {
            return self::{'_'.$key.'QueryString'}($value);
        }
        if (is_array($value)) {
            
            foreach ($value as $op => $opVal) {
                
                if (in_array($op, self::$bucketOperators)) {
                    return self::{'_'.$op.'QueryString'}($opVal);
                }
                if (in_array($op, self::$equivalenceOperators)) {
                    return self::{'_'.$op.'QueryString'}($key, $opVal);
                }
                if (in_array($op, self::$clauseOperators)) {
                    switch ($op) {
                        case 'ne':
                            if (!$opVal) {
                                // Is not equal to null => exists and has a value
                                return '(_exists_:'.$key.')';
                            }
                            
                            return '(NOT '.$key.':'.self::_escape($opVal).')';
                        case 'lt':
                            return '('.$key.':{* TO '.$opVal.'})';
                        case 'lte':
                            return '('.$key.':[* TO '.$opVal.'])';
                        case 'gt':
                            return '('.$key.':{'.$opVal.' TO *})';
                        case 'gte':
                            return '('.$key.':['.$opVal.' TO *])';
                        case 'between':
                            return '('.$key.':['.$opVal[0].' TO '.$opVal[1].'])';
                        case 'not_between':
                            return '(NOT '.$key.':['.$opVal[0].' TO '.$opVal[1].'])';
                        case 'like':
                            return '('.$key.':*'.self::_escape($opVal).'*)';
                        case 'not_like':
                            return '(NOT '.$key.':*'.self::_escape($opVal).'*)';
                        case 'regex':
                            return '('.$key.':/'.$opVal.'/)';
                        case 'exists':
                            if ($opVal) {
                                return '(_exists_:'.$key.')';
                            }
                            
                            return '(NOT _exists_:'.$key.')';
                        
                    }
                    
                }
                
                return self::_parseParams($op, $opVal);
            }
        }
        
        if (!$key) {
            return self::_escape($value);
        }
        if ($value === true) {
            return '('.$key.':true)';
        }
        if ($value === false) {
            return '('.$key.':false)';
        }
        if ($value === null) {
            return '(NOT _exists_:'.$key.')';
        }
        
        return '('.$key.':"'.self::_escape($value).'")';
        
    }
    
    public static function _escape($value): string
    {
        $specialChars = ['"', '\\', '~', '^'];
        foreach ($specialChars as $char) {
            $value = str_replace($char, "\\".$char, $value);
        }
        
        return $value;
    }
    
    private function _buildQuery($wheres): array
    {
        if (!$wheres) {
            return ParameterBuilder::matchAll();
        }
        $string = $this->_buildQueryString($wheres);
        
        return ParameterBuilder::queryStringQuery($string);
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
        if (!empty($body['query']['match_all'])) {
            $filteredBody = [
                'query' => [
                    'bool' => [
                        'must'   => [
                            'match_all' => $body['query']['match_all'],
                        ],
                        'filter' => $filer['filter'],
                    ],
                ],
            ];
            $params['body']['query'] = $filteredBody['query'];
        }
        if (!empty($body['query']['query_string'])) {
            $filteredBody = [
                'query' => [
                    'bool' => [
                        'must'   => [
                            'query_string' => $body['query']['query_string'],
                        ],
                        'filter' => $filer['filter'],
                    ],
                ],
            ];
            $params['body']['query'] = $filteredBody['query'];
        }
        
        return $params;
    }
}

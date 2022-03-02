<?php

namespace PDPhilip\Elasticsearch\DSL;


use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use ONGR\ElasticsearchDSL\Query\MatchAllQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use Exception;

trait QueryBuilder
{

    protected static $bucketOperators = ['and', 'or'];

    protected static $equivalenceOperators = ['in', 'nin'];

    protected static $clauseOperators = ['ne', 'gt', 'gte', 'lt', 'lte', 'between', 'not_between', 'like', 'not_like', 'exists'];

    public static function buildParams($index, $wheres, $options = [], $columns = [], $_id = null)
    {
        if ($index){
            $params = [
                'index' => $index
            ];
        }

        if ($_id) {
            $params['id'] = $_id;
        }

        $params['body'] = self::_buildQuery($wheres);
        if ($columns && $columns != '*') {
            $params['body']['_source'] = $columns;
        }

        $opts = self::_buildOptions($options);
        if ($opts) {
            foreach ($opts as $key => $value) {
                if (isset($params[$key])) {
                    $params[$key] = array_merge($params[$key], $opts[$key]);
                } else {
                    $params[$key] = $value;
                }
            }
        }

        return $params;
    }

    private static function _buildQueryString($wheres): string
    {
        if ($wheres) {
            foreach ($wheres as $key => $value) {
                return self::_parseParams($key, $value);
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

        if ($key == 'and' || $key == 'or') {
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

    private static function _escape($string): string
    {
        //+ - = && || > < ! ( ) { } [ ] ^ " ~ * ? : \ /
        $stripped = preg_replace('/\W/', '\\\\$0', $string);

        //Put the spaces back;
        $stripped = str_replace('\ ', ' ', $stripped);
        //Edge cases
        $stripped = str_replace('\&\&', '\&&', $stripped);
        $stripped = str_replace('\|\|', '\||', $stripped);

        return $stripped;

    }

    private static function _buildQuery($wheres): array
    {
        $search = new Search();
        if (!$wheres) {
            $search->addQuery(new MatchAllQuery());
            $search->toArray();

            return $search->toArray();
        }
        $string = self::_buildQueryString($wheres);
        $queryStringQuery = new QueryStringQuery($string);
        $search->addQuery($queryStringQuery);

        return $search->toArray();

    }

    private static function _buildOptions($options): array
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
                        $sortBy = self::_parseSortOrder($value);
                        $return['body']['sort'][] = $sortBy;
                        break;
                    case 'skip':
                        $return['from'] = $value;
                        break;
                    case 'multiple':
                        break;
                    default:
                        throw new Exception('Unexpected option: '.$key);
                }
            }
        }

        return $return;
    }

    private static function _parseSortOrder($value): array
    {
        $field = array_key_first($value);
        $direction = $value[$field];

        $dir = 'desc';
        if ($direction == 1) {
            $dir = 'asc';
        }
        $sort = new FieldSort($field, $dir);

        return $sort->toArray();
    }
}

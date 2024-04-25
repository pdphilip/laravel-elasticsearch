<?php

namespace PDPhilip\Elasticsearch\Query;

use Carbon\Carbon;
use PDPhilip\Elasticsearch\Connection;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use PDPhilip\Elasticsearch\DSL\QueryBuilder;
use PDPhilip\Elasticsearch\Schema\Schema;
use RuntimeException;
use LogicException;

class Builder extends BaseBuilder
{
    
    use QueryBuilder;
    
    protected $index;
    
    protected $refresh = 'wait_for';
    
    public $options = [];
    
    public $paginating = false;
    
    public $searchQuery = '';
    
    public $searchOptions = [];
    
    public $minScore = null;
    
    public $fields = [];
    
    public $filters = [];
    
    /**
     * Clause ops.
     *
     * @var string[]
     */
    public $operators = [
        // @inherited
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>', '&~',
        'rlike', 'not rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*',
        // @Elastic Search
        'exist', 'regex',
    ];
    
    /**
     * Operator conversion.
     *
     * @var array
     */
    protected $conversion = [
        '='  => '=',
        '!=' => 'ne',
        '<>' => 'ne',
        '<'  => 'lt',
        '<=' => 'lte',
        '>'  => 'gt',
        '>=' => 'gte',
    ];
    
    /**
     * @inheritdoc
     */
    public function __construct(Connection $connection, Processor $processor)
    {
        $this->grammar = new Grammar;
        $this->connection = $connection;
        $this->processor = $processor;
        
    }
    
    
    public function setRefresh($value)
    {
        $this->refresh = $value;
    }
    
    
    //----------------------------------------------------------------------
    // Querying Executors
    //----------------------------------------------------------------------
    
    /**
     * @inheritdoc
     */
    public function find($id, $columns = [])
    {
        return $this->where('_id', $id)->first($columns);
    }
    
    /**
     * @inheritdoc
     */
    public function value($column)
    {
        $result = (array)$this->first([$column]);
        
        return Arr::get($result, $column);
    }
    
    /**
     * @inheritdoc
     */
    public function all($columns = [])
    {
        return $this->_processGet($columns);
    }
    
    /**
     * @inheritdoc
     */
    public function get($columns = [])
    {
        return $this->_processGet($columns);
    }
    
    /**
     * @inheritdoc
     */
    public function cursor($columns = [])
    {
        $result = $this->_processGet($columns, true);
        if ($result instanceof LazyCollection) {
            return $result;
        }
        throw new RuntimeException('Query not compatible with cursor');
    }
    
    /**
     * @inheritdoc
     */
    public function exists()
    {
        return $this->first() !== null;
    }
    
    /**
     * @inheritdoc
     */
    public function insert(array $values)
    {
        if (empty($values)) {
            return true;
        }
        
        if (!is_array(reset($values))) {
            $values = [$values];
        }
        
        $allSuccess = true;
        foreach ($values as $value) {
            $result = $this->_processInsert($value, true);
            if (!$result) {
                $allSuccess = false;
            }
        }
        
        return $allSuccess;
    }
    
    /**
     * @inheritdoc
     */
    public function insertGetId(array $values, $sequence = null)
    {
        //Also Model->save()
        return $this->_processInsert($values, true);
    }
    
    /**
     * @inheritdoc
     */
    public function update(array $values, array $options = [])
    {
        $this->_checkValues($values);
        
        return $this->_processUpdate($values, $options);
    }
    
    /**
     * @inheritdoc
     */
    public function increment($column, $amount = 1, $extra = [], $options = [])
    {
        $values = ['inc' => [$column => $amount]];
        
        if (!empty($extra)) {
            $values['set'] = $extra;
        }
        
        $this->where(function ($query) use ($column) {
            $query->where($column, 'exists', false);
            
            $query->orWhereNotNull($column);
        });
        
        
        return $this->_processUpdate($values, $options, 'incrementMany');
    }
    
    /**
     * @inheritdoc
     */
    public function decrement($column, $amount = 1, $extra = [], $options = [])
    {
        return $this->increment($column, -1 * $amount, $extra, $options);
    }


//
    
    /**
     * @inheritdoc
     */
    public function forPageAfterId($perPage = 15, $lastId = 0, $column = '_id')
    {
        return parent::forPageAfterId($perPage, $lastId, $column);
    }
    
    /**
     * @inheritdoc
     */
    public function delete($id = null)
    {
        
        if ($id !== null) {
            $this->where('_id', '=', $id);
        }
        
        return $this->_processDelete();
        
    }
    
    /**
     * @inheritdoc
     */
    public function aggregate($function, $columns = [])
    {
        
        $this->aggregate = compact('function', 'columns');
        
        $previousColumns = $this->columns;
        
        // Store previous bindings before aggregate
        $previousSelectBindings = $this->bindings['select'];
        
        $this->bindings['select'] = [];
        $results = $this->get($columns);
        
        // Restore bindings after aggregate search
        $this->aggregate = null;
        $this->columns = $previousColumns;
        $this->bindings['select'] = $previousSelectBindings;
        
        if (isset($results[0])) {
            $result = (array)$results[0];
            
            return $result['aggregate'];
        }
        
        return null;
    }
    
    /**
     * @param $column
     * @param $callBack
     * @param $scoreMode
     *
     * @return $this
     */
    public function whereNestedObject($column, $callBack, $scoreMode = 'avg')
    {
        $boolean = 'and';
        $query = $this->newQuery();
        $callBack($query);
        $wheres = $query->compileWheres();
        $this->wheres[] = [
            'column'     => $column,
            'type'       => 'NestedObject',
            'wheres'     => $wheres,
            'score_mode' => $scoreMode,
            'boolean'    => $boolean,
        ];
        
        return $this;
    }
    
    /**
     * @param $column
     * @param $callBack
     * @param $scoreMode
     *
     * @return $this
     */
    public function whereNotNestedObject($column, $callBack, $scoreMode = 'avg')
    {
        $boolean = 'and';
        $query = $this->newQuery();
        $callBack($query);
        $wheres = $query->compileWheres();
        $this->wheres[] = [
            'column'     => $column,
            'type'       => 'NotNestedObject',
            'wheres'     => $wheres,
            'score_mode' => $scoreMode,
            'boolean'    => $boolean,
        ];
        
        return $this;
    }
    
    /**
     * @param $column
     * @param $value
     *
     * @return $this
     */
    public function wherePhrase($column, $value)
    {
        $boolean = 'and';
        $this->wheres[] = [
            'column'   => $column,
            'type'     => 'Basic',
            'value'    => $value,
            'operator' => 'phrase',
            'boolean'  => $boolean,
        ];
        
        return $this;
    }
    
    /**
     * @param $column
     * @param $value
     *
     * @return $this
     */
    public function whereExact($column, $value)
    {
        $boolean = 'and';
        $this->wheres[] = [
            'column'   => $column,
            'type'     => 'Basic',
            'value'    => $value,
            'operator' => 'exact',
            'boolean'  => $boolean,
        ];
        
        return $this;
    }
    
    
    /**
     * @param $column
     * @param $callBack
     *
     * @return $this
     */
    public function queryNested($column, $callBack)
    {
        $boolean = 'and';
        $query = $this->newQuery();
        $callBack($query);
        $wheres = $query->compileWheres();
        $options = $query->compileOptions();
        $this->wheres[] = [
            'column'  => $column,
            'type'    => 'QueryNested',
            'wheres'  => $wheres,
            'options' => $options,
            'boolean' => $boolean,
        ];
        
        return $this;
    }
    
    public function whereTimestamp($column, $operator = null, $value = null, $boolean = 'and')
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );
        if ($this->invalidOperator($operator)) {
            [$value, $operator] = [$operator, '='];
        }
        $this->wheres[] = [
            'column'   => $column,
            'type'     => 'Timestamp',
            'value'    => $value,
            'operator' => $operator,
            'boolean'  => $boolean,
        ];
        
        return $this;
    }
    
    
    //----------------------------------------------------------------------
    //  Query Processing (Connection API)
    //----------------------------------------------------------------------
    
    /**
     * @param    array    $columns
     * @param    false    $returnLazy
     *
     * @return Collection|LazyCollection|void
     */
    protected function _processGet($columns = [], $returnLazy = false)
    {
        
        $wheres = $this->compileWheres();
        $options = $this->compileOptions();
        $columns = $this->prepareColumns($columns);
        
        if ($this->groups) {
            throw new RuntimeException('Groups are not used');
        }
        
        if ($this->aggregate) {
            $function = $this->aggregate['function'];
            $aggColumns = $this->aggregate['columns'];
            if (in_array('*', $aggColumns)) {
                $aggColumns = null;
                
            }
            if ($aggColumns) {
                $columns = $aggColumns;
            }
            
            if ($this->distinct) {
                $totalResults = $this->connection->distinctAggregate($function, $wheres, $options, $columns);
            } else {
                $totalResults = $this->connection->aggregate($function, $wheres, $options, $columns);
            }
            
            if (!$totalResults->isSuccessful()) {
                throw new RuntimeException($totalResults->errorMessage);
            }
            $results = [
                [
                    '_id'       => null,
                    'aggregate' => $totalResults->data,
                ],
            ];
            
            // Return results
            return new Collection($results);
            
        }
        
        if ($this->distinct) {
            if (empty($columns[0]) || $columns[0] == '*') {
                throw new RuntimeException('Columns are required for term aggregation when using distinct()');
            } else {
                if ($this->distinct == 2) {
                    $find = $this->connection->distinct($wheres, $options, $columns, true);
                } else {
                    $find = $this->connection->distinct($wheres, $options, $columns);
                }
                
            }
            
        } else {
            $find = $this->connection->find($wheres, $options, $columns);
        }
        
        //Else Normal find query
        if ($find->isSuccessful()) {
            $data = $find->data;
            if ($returnLazy) {
                if ($data) {
                    return LazyCollection::make(function () use ($data) {
                        foreach ($data as $item) {
                            yield $item;
                        }
                    });
                }
                
            }
            
            return new Collection($data);
        } else {
            throw new RuntimeException('Error: '.$find->errorMessage);
        }
        
    }
    
    /**
     * @param $query
     * @param    array    $options
     * @param    string    $method
     *
     * @return int
     */
    protected function _processUpdate($values, array $options = [], $method = 'updateMany')
    {
        // Update multiple items by default.
        if (!array_key_exists('multiple', $options)) {
            $options['multiple'] = true;
        }
        $wheres = $this->compileWheres();
        $result = $this->connection->{$method}($wheres, $values, $options, $this->refresh);
        if ($result->isSuccessful()) {
            return $result->getModifiedCount();
        }
        
        return 0;
    }
    
    
    /**
     * @param    array    $values
     * @param    false    $returnIdOnly
     *
     * @return null|string|array
     */
    protected function _processInsert(array $values, $returnIdOnly = false)
    {
        $result = $this->connection->save($values, $this->refresh);
        
        if ($result->isSuccessful()) {
            
            // Return id
            return $returnIdOnly ? $result->getInsertedId() : $result->data;
        }
        
        return null;
    }
    
    /**
     * @return int
     */
    protected function _processDelete()
    {
        $wheres = $this->compileWheres();
        $options = $this->compileOptions();
        $result = $this->connection->deleteAll($wheres, $options);
        if ($result->isSuccessful()) {
            return $result->getDeletedCount();
        }
        
        return 0;
    }
    
    
    //----------------------------------------------------------------------
    // Clause Operators
    //----------------------------------------------------------------------
    
    /**
     * @inheritdoc
     */
    public function orderBy($column, $direction = 'asc', $mode = null, $missing = null)
    {
        if (is_string($direction)) {
            $direction = (strtolower($direction) == 'asc' ? 'asc' : 'desc');
        }
        
        $this->orders[$column] = [
            'order'   => $direction,
            'mode'    => $mode,
            'missing' => $missing,
        ];

//        dd($this->orders);
        
        return $this;
    }
    
    /**
     * @inheritDoc
     */
    public function orderByDesc($column, $mode = null, $missing = null)
    {
        return $this->orderBy($column, 'desc', $mode, $missing);
    }
    
    /**
     * @param $column
     * @param $pin
     * @param $direction    @values: 'asc', 'desc'
     * @param $unit    @values: 'km', 'mi', 'm', 'ft'
     * @param $mode    @values: 'min', 'max', 'avg', 'sum'
     * @param $type    @values: 'arc', 'plane'
     *
     * @return $this
     */
    public function orderByGeo($column, $pin, $direction = 'asc', $unit = 'km', $mode = null, $type = null)
    {
        $this->orders[$column] = [
            'is_geo' => true,
            'order'  => $direction,
            'pin'    => $pin,
            'unit'   => $unit,
            'mode'   => $mode,
            'type'   => $type,
        ];
        
        return $this;
    }
    
    /**
     * @param $column
     * @param $pin
     * @param $unit    @values: 'km', 'mi', 'm', 'ft'
     * @param $mode    @values: 'min', 'max', 'avg', 'sum'
     * @param $type    @values: 'arc', 'plane'
     *
     * @return $this
     */
    public function orderByGeoDesc($column, $pin, $unit = 'km', $mode = null, $type = null)
    {
        return $this->orderByGeo($column, $pin, 'desc', $unit, $mode, $type);
    }
    
    
    /**
     * @param $column
     * @param $direction
     * @param $mode
     *
     * @return $this
     */
    public function orderByNested($column, $direction = 'asc', $mode = null)
    {
        $this->orders[$column] = [
            'is_nested' => true,
            'order'     => $direction,
            'mode'      => $mode,
        
        ];
        
        return $this;
    }
    
    
    /**
     * @inheritdoc
     */
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false)
    {
        $type = 'between';
        
        $this->wheres[] = compact('column', 'type', 'boolean', 'values', 'not');
        
        return $this;
    }
    
    /**
     * @inheritdoc
     */
    public function select($columns = ['*'])
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $this->columns = $columns;
        
        return $this;
    }
    
    public function addSelect($column)
    {
        if (!is_array($column)) {
            $column = [$column];
        }
        
        $currentColumns = $this->columns;
        if ($currentColumns) {
            return $this->select(array_merge($currentColumns, $column));
        }
        
        return $this->select($column);
        
    }
    
    /**
     * @inheritdoc
     */
    
    public function distinct($includeCount = false)
    {
        $this->distinct = 1;
        if ($includeCount) {
            $this->distinct = 2;
        }
        
        return $this;
    }
    
    /**
     * @param ...$groups
     *
     * GroupBy will be passed on to distinct
     *
     * @return $this|Builder
     */
    public function groupBy(...$groups)
    {
        if (is_array($groups[0])) {
            $groups = $groups[0];
        }
        
        $this->addSelect($groups);
        $this->distinct = 1;
        
        return $this;
    }
    
    //Filters
    
    public function filterGeoBox($field, $topLeft, $bottomRight)
    {
        $this->filters['filterGeoBox'] = [
            'field'       => $field,
            'topLeft'     => $topLeft,
            'bottomRight' => $bottomRight,
        ];
    }
    
    public function filterGeoPoint($field, $distance, $geoPoint)
    {
        $this->filters['filterGeoPoint'] = [
            'field'    => $field,
            'distance' => $distance,
            'geoPoint' => $geoPoint,
        ];
    }
    
    //Regexs
    
    public function whereRegex($column, $expression)
    {
        $type = 'regex';
        $boolean = 'and';
        $this->wheres[] = compact('column', 'type', 'expression', 'boolean');
        
        return $this;
    }
    
    public function orWhereRegex($column, $expression)
    {
        $type = 'regex';
        $boolean = 'or';
        $this->wheres[] = compact('column', 'type', 'expression', 'boolean');
        
        return $this;
    }
    
    /**
     * @inheritdoc
     */
    public function newQuery()
    {
        return new self($this->connection, $this->processor);
    }
    
    protected function prepareColumns($columns)
    {
        $final = [];
        if ($this->columns) {
            foreach ($this->columns as $col) {
                $final[] = $col;
            }
            
        }
        
        if ($columns) {
            if (!is_array($columns)) {
                $columns = [$columns];
            }
            
            foreach ($columns as $col) {
                $final[] = $col;
            }
        }
        if (!$final) {
            return ['*'];
        }
        
        $final = array_values(array_unique($final));
        if (($key = array_search('*', $final)) !== false) {
            unset($final[$key]);
        }
        
        return $final;
        
        
    }
    
    protected function compileOptions()
    {
        $options = [];
        if ($this->orders) {
            $options['sort'] = $this->orders;
        }
        if ($this->offset) {
            $options['skip'] = $this->offset;
        }
        if ($this->limit) {
            $options['limit'] = $this->limit;
            //Check if it's first() with no ordering,
            //Set order to created_at -> asc for consistency
            //TODO
        }
        if ($this->minScore) {
            $options['minScore'] = $this->minScore;
        }
        if ($this->searchOptions) {
            $options['searchOptions'] = $this->searchOptions;
        }
        if ($this->filters) {
            $options['filters'] = $this->filters;
        }
        
        return $options;
    }
    
    /**
     * @return array
     */
    protected function compileWheres()
    {
        $wheres = $this->wheres ? : [];
        $compiledWheres = [];
        if ($wheres) {
            if ($wheres[0]['boolean'] == 'or') {
                throw new RuntimeException('Cannot start a query with an OR statement');
            }
            if (count($wheres) == 1) {
                return $this->{'_parseWhere'.$wheres[0]['type']}($wheres[0]);
            }
            $and = [];
            $or = [];
            foreach ($wheres as $where) {
                if ($where['boolean'] == 'or') {
                    $or[] = $and;
                    //clear AND for the next bucket
                    $and = [];
                }
                
                $result = $this->{'_parseWhere'.$where['type']}($where);
                $and[] = $result;
                
            }
            if ($or) {
                //Add the last AND bucket
                $or[] = $and;
                foreach ($or as $and) {
                    $compiledWheres['or'][] = $this->_prepAndBucket($and);
                }
            } else {
                
                $compiledWheres = $this->_prepAndBucket($and);
            }
        }
        
        return $compiledWheres;
    }
    
    private function _prepAndBucket($andData)
    {
        $data = [];
        foreach ($andData as $key => $ops) {
            $data['and'][$key] = $ops;
        }
        
        return $data;
    }
    
    /**
     * @param    array    $where
     *
     * @return array
     */
    protected function _parseWhereBasic(array $where)
    {
        $operator = $where['operator'];
        $column = $where['column'];
        $value = $where['value'];
        $boolean = $where['boolean'] ?? null;
        if ($boolean === 'and not') {
            $operator = '!=';
        }
        if ($boolean === 'or not') {
            $operator = '!=';
        }
        if ($operator === 'not like') {
            $operator = 'not_like';
        }
        
        if (!isset($operator) || $operator == '=') {
            $query = [$column => $value];
        } elseif (array_key_exists($operator, $this->conversion)) {
            $query = [$column => [$this->conversion[$operator] => $value]];
        } else {
            if (is_callable($column)) {
                throw new RuntimeException('Invalid closure for where clause');
            }
            $query = [$column => [$operator => $value]];
        }
        
        return $query;
    }
    
    /**
     * @param    array    $where
     *
     * @return mixed
     */
    protected function _parseWhereNested(array $where)
    {
        
        $boolean = $where['boolean'];
//        if ($boolean !== 'and') {
//            throw new RuntimeException('Nested where clause with boolean other than "and" is not supported');
//        }
        if ($boolean === 'and not') {
            $boolean = 'not';
        }
        $must = match ($boolean) {
            'and' => 'must',
            'not', 'or not' => 'must_not',
            'or' => 'should',
            default => throw new RuntimeException($boolean.' is not supported for parameter grouping'),
        };
        
        $query = $where['query'];
        $wheres = $query->compileWheres();
        
        return [
            $must => ['group' => ['wheres' => $wheres]],
        ];
        
        
    }
    
    protected function _parseWhereQueryNested(array $where)
    {
        return [
            $where['column'] => [
                'innerNested' => [
                    'wheres'  => $where['wheres'],
                    'options' => $where['options'],
                ],
            ],
        ];
    }
    
    /**
     * @param    array    $where
     *
     * @return array
     */
    protected function _parseWhereIn(array $where)
    {
        $column = $where['column'];
        $values = $where['values'];
        
        return [$column => ['in' => array_values($values)]];
    }
    
    
    /**
     * @param    array    $where
     *
     * @return array
     */
    protected function _parseWhereNotIn(array $where)
    {
        $column = $where['column'];
        $values = $where['values'];
        
        return [$column => ['nin' => array_values($values)]];
    }
    
    /**
     * @param    array    $where
     *
     * @return array
     */
    protected function _parseWhereNull(array $where)
    {
        $where['operator'] = 'not_exists';
        $where['value'] = null;
        
        return $this->_parseWhereBasic($where);
    }
    
    /**
     * @param    array    $where
     *
     * @return array
     */
    protected function _parseWhereNotNull(array $where)
    {
        $where['operator'] = 'exists';
        $where['value'] = null;
        
        return $this->_parseWhereBasic($where);
    }
    
    /**
     * @param    array    $where
     *
     * @return array
     */
    protected function _parseWhereBetween(array $where)
    {
        $not = $where['not'] ?? false;
        $values = $where['values'];
        $column = $where['column'];
        
        if ($not) {
            return [
                $column => [
                    'not_between' => [$values[0], $values[1]],
                ],
            ];
        }
        
        return [
            $column => [
                'between' => [$values[0], $values[1]],
            ],
        ];
    }
    
    /**
     * @param    array    $where
     *
     * @return array
     */
    protected function _parseWhereDate(array $where)
    {
        //return a normal where clause
        return $this->_parseWhereBasic($where);
    }
    
    protected function _parseWhereTimestamp(array $where)
    {
        $where['value'] = $this->_formatTimestamp($where['value']);
        
        return $this->_parseWhereBasic($where);
        
    }
    
    /**
     * @param    array    $where
     *
     * @return array
     */
    protected function _parseWhereMonth(array $where)
    {
        throw new LogicException('whereMonth clause is not available yet');
        
    }
    
    /**
     * @param    array    $where
     *
     * @return array
     */
    protected function _parseWhereDay(array $where)
    {
        throw new LogicException('whereDay clause is not available yet');
        
    }
    
    /**
     * @param    array    $where
     *
     * @return array
     */
    protected function _parseWhereYear(array $where)
    {
        throw new LogicException('whereYear clause is not available yet');
        
    }
    
    /**
     * @param    array    $where
     *
     * @return array
     */
    protected function _parseWhereTime(array $where)
    {
        throw new LogicException('whereTime clause is not available yet');
        
    }
    
    /**
     * @param    array    $where
     *
     * @return mixed
     */
    protected function _parseWhereRaw(array $where)
    {
        throw new LogicException('whereRaw clause is not available yet');
        
    }
    
    public function _parseWhereExists(array $where)
    {
        throw new LogicException('SQL type "where exists" query is not valid for Elasticsearch. Use whereNotNull() or whereNull() to query the existence of a field');
    }
    
    public function _parseWhereNotExists(array $where)
    {
        throw new LogicException('SQL type "where exists" query is not valid for Elasticsearch. Use whereNotNull() or whereNull() to query the existence of a field');
    }
    
    
    /**
     * @param    array    $where
     *
     * @return mixed
     */
    protected function _parseWhereRegex(array $where)
    {
        $value = $where['expression'];
        $column = $where['column'];
        
        return [$column => ['regex' => $value]];
        
    }
    
    /**
     * @param    array    $where
     *
     * @return array[]
     */
    protected function _parseWhereNestedObject(array $where)
    {
        $wheres = $where['wheres'];
        $column = $where['column'];
        $scoreMode = $where['score_mode'];
        
        
        return [
            $column => ['nested' => ['wheres' => $wheres, 'score_mode' => $scoreMode]],
        ];
    }
    
    
    /**
     * @param    array    $where
     *
     * @return array[]
     */
    protected function _parseWhereNotNestedObject(array $where)
    {
        $wheres = $where['wheres'];
        $column = $where['column'];
        $scoreMode = $where['score_mode'];
        
        
        return [
            $column => ['not_nested' => ['wheres' => $wheres, 'score_mode' => $scoreMode]],
        ];
    }
    
    
    /**
     * Set custom options for the query.
     *
     * @param    array    $options
     *
     * @return $this
     */
    public function options(array $options)
    {
        $this->options = $options;
        
        return $this;
    }
    
    
    //----------------------------------------------------------------------
    // Collection bindings
    //----------------------------------------------------------------------
    
    /**
     * @inheritdoc
     */
    public function pluck($column, $key = null)
    {
        $results = $this->get($key === null ? [$column] : [$column, $key]);
        
        // Convert ObjectID's to strings
        if ($key == '_id') {
            $results = $results->map(function ($item) {
                $item['_id'] = (string)$item['_id'];
                
                return $item;
            });
        }
        
        $p = Arr::pluck($results, $column, $key);
        
        return new Collection($p);
    }
    
    //----------------------------------------------------------------------
    // Index/Schema
    //----------------------------------------------------------------------
    
    /**
     * @inheritdoc
     */
    public function from($index, $as = null)
    {
        
        if ($index) {
            $this->connection->setIndex($index);
            $this->index = $this->connection->getIndex();
        }
        
        return parent::from($index);
    }
    
    /**
     * @inheritdoc
     */
    public function truncate()
    {
        $result = $this->connection->deleteAll([]);
        
        if ($result->isSuccessful()) {
            return $result->getDeletedCount();
        }
        
        return 0;
    }
    
    public function deleteIndex()
    {
        return Schema::delete($this->index);
        
    }
    
    public function deleteIndexIfExists()
    {
        return Schema::deleteIfExists($this->index);
        
    }
    
    public function getIndexMappings()
    {
        return Schema::getMappings($this->index);
    }
    
    public function getIndexSettings()
    {
        return Schema::getSettings($this->index);
    }
    
    public function indexExists()
    {
        return Schema::hasIndex($this->index);
    }
    
    public function createIndex()
    {
        if (!$this->indexExists()) {
            $this->connection->indexCreate($this->index);
            
            return true;
        }
        
        return false;
    }
    
    public function rawSearch(array $bodyParams)
    {
        $find = $this->connection->searchRaw($bodyParams);
        $data = $find->data;
        
        return new Collection($data);
        
    }
    
    public function rawAggregation(array $bodyParams)
    {
        $find = $this->connection->aggregationRaw($bodyParams);
        $data = $find->data;
        
        return new Collection($data);
        
    }
    //----------------------------------------------------------------------
    // Pagination overrides
    //----------------------------------------------------------------------
    
    
    protected function runPaginationCountQuery($columns = ['*'])
    {
        if ($this->distinct) {
            $clone = $this->cloneForPaginationCount();
            $currentCloneCols = $clone->columns;
            if ($columns && $columns !== ['*']) {
                $currentCloneCols = array_merge($currentCloneCols, $columns);
            }
            
            return $clone->setAggregate('count', $currentCloneCols)->get()->all();
        }
        
        $without = $this->unions ? ['orders', 'limit', 'offset'] : ['columns', 'orders', 'limit', 'offset'];
        
        return $this->cloneWithout($without)
            ->cloneWithoutBindings($this->unions ? ['order'] : ['select', 'order'])
            ->setAggregate('count', $this->withoutSelectAliases($columns))
            ->get()->all();
    }
    
    //----------------------------------------------------------------------
    // Disabled features (for now)
    //----------------------------------------------------------------------
    
    /**
     * @inheritdoc
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new LogicException('The upsert feature for Elasticsearch is currently not supported. Please use updateAll()');
    }
    
    
    /**
     * @inheritdoc
     */
    public function groupByRaw($sql, array $bindings = [])
    {
        throw new LogicException('groupByRaw() is currently not supported');
    }
    
    
    //----------------------------------------------------------------------
    // Helpers
    //----------------------------------------------------------------------
    
    private function _checkValues($values)
    {
        unset($values['updated_at']);
        unset($values['created_at']);
        if (!$this->_isAssociative($values)) {
            throw new RuntimeException('Invalid value format. Expected associative array, got sequential array');
        }
        
        return true;
    }
    
    private function _isAssociative(array $arr)
    {
        if ([] === $arr) {
            return false;
        }
        
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
    
    
    //----------------------------------------------------------------------
    // ES query executors
    //----------------------------------------------------------------------
    
    public function query($columns = [])
    {
        $wheres = $this->compileWheres();
        $options = $this->compileOptions();
        
        return $this->connection->showQuery($wheres, $options, $columns);
    }
    
    public function matrix($column)
    {
        if (!is_array($column)) {
            $column = [$column];
        }
        $result = $this->aggregate(__FUNCTION__, $column);
        
        return $result ? : 0;
    }
    
    //----------------------------------------------------------------------
    // ES Search query methods
    //----------------------------------------------------------------------
    
    
    public function searchQuery($term, $boostFactor = null, $clause = null, $type = 'term')
    {
        if (!$clause && !empty($this->searchQuery)) {
            switch ($type) {
                case 'fuzzy':
                    throw new RuntimeException('Incorrect query sequencing, searchFuzzyTerm() should only start the ORM chain');
                case 'regex':
                    throw new RuntimeException('Incorrect query sequencing, searchRegEx() should only start the ORM chain');
                default:
                    throw new RuntimeException('Incorrect query sequencing, searchTerm() should only start the ORM chain');
            }
            
        }
        if ($clause && empty($this->searchQuery)) {
            switch ($type) {
                case 'fuzzy':
                    throw new RuntimeException('Incorrect query sequencing, andFuzzyTerm()/orFuzzyTerm() cannot start the ORM chain');
                case 'regex':
                    throw new RuntimeException('Incorrect query sequencing, andRegEx()/orRegEx() cannot start the ORM chain');
                default:
                    throw new RuntimeException('Incorrect query sequencing, andTerm()/orTerm() cannot start the ORM chain');
            }
            
        }
        switch ($type) {
            case 'fuzzy':
                $nextTerm = '('.self::_escape($term).'~)';
                break;
            case 'regex':
                $nextTerm = '(/'.$term.'/)';
                break;
            default:
                $nextTerm = '('.self::_escape($term).')';
                break;
        }
        
        if ($boostFactor) {
            $nextTerm .= '^'.$boostFactor;
        }
        if ($clause) {
            $this->searchQuery = $this->searchQuery.' '.strtoupper($clause).' '.$nextTerm;
        } else {
            $this->searchQuery = $nextTerm;
        }
    }
    
    public function minShouldMatch($value)
    {
        $this->searchOptions['minimum_should_match'] = $value;
    }
    
    public function minScore($value)
    {
        $this->minScore = $value;
    }
    
    public function boostField($field, $factor)
    {
        $this->fields[$field] = $factor ?? 1;
    }
    
    public function searchFields(array $fields)
    {
        foreach ($fields as $field) {
            if (empty($this->fields[$field])) {
                $this->fields[$field] = 1;
            }
        }
    }
    
    public function searchField($field, $boostFactor = null)
    {
        $this->fields[$field] = $boostFactor ?? 1;
    }
    
    public function highlight(array $fields = [], string|array $preTag = '<em>', string|array $postTag = '</em>', array $globalOptions = [])
    {
        $highlightFields = [
            '*' => (object)[],
        ];
        if (!empty($fields)) {
            $highlightFields = [];
            foreach ($fields as $field => $payload) {
                if (is_int($field)) {
                    $highlightFields[$payload] = (object)[];
                } else {
                    $highlightFields[$field] = $payload;
                }
                
            }
        }
        if (!is_array($preTag)) {
            $preTag = [$preTag];
        }
        if (!is_array($postTag)) {
            $postTag = [$postTag];
        }
        
        $highlight = [];
        if ($globalOptions) {
            $highlight = $globalOptions;
        }
        $highlight['pre_tags'] = $preTag;
        $highlight['post_tags'] = $postTag;
        $highlight['fields'] = $highlightFields;
        
        
        $this->searchOptions['highlight'] = $highlight;
    }
    
    
    public function search($columns = '*')
    {
        
        $searchParams = $this->searchQuery;
        if (!$searchParams) {
            throw new RuntimeException('No search parameters. Add terms to search for.');
        }
        $searchOptions = $this->searchOptions;
        $wheres = $this->compileWheres();
        $options = $this->compileOptions();
        $fields = $this->fields;
        
        $search = $this->connection->search($searchParams, $searchOptions, $wheres, $options, $fields, $columns);
        if ($search->isSuccessful()) {
            $data = $search->data;
            
            
            return new Collection($data);
            
            
        } else {
            throw new RuntimeException('Error: '.$search->errorMessage);
        }
        
        
    }
    
    //----------------------------------------------------------------------
    // PIT API
    //----------------------------------------------------------------------
    
    public function openPit($keepAlive = '5m')
    {
        return $this->connection->openPit($keepAlive);
    }
    
    public function pitFind($count, $pitId, $after = null, $keepAlive = '5m')
    {
        $wheres = $this->compileWheres();
        $options = $this->compileOptions();
        $fields = $this->fields;
        $options['limit'] = $count;
        
        return $this->connection->pitFind($wheres, $options, $fields, $pitId, $after, $keepAlive);
    }
    
    public function closePit($id)
    {
        return $this->connection->closePit($id);
    }
    
    
    //----------------------------------------------------------------------
    // Helpers
    //----------------------------------------------------------------------
    
    private function _formatTimestamp($value)
    {
        if (is_numeric($value)) {
            // Convert to integer in case it's a string
            $value = (int)$value;
            // Check for milliseconds
            if ($value > 10000000000) {
                return $value;
            }
            
            // ES expects seconds as a string
            return (string)Carbon::createFromTimestamp($value)->timestamp;
        }
        
        // If it's not numeric, assume it's a date string and try to return TS as a string
        try {
            return (string)Carbon::parse($value)->timestamp;
        } catch (\Exception $e) {
            throw new LogicException('Invalid date or timestamp');
        }
    }
    
    
}

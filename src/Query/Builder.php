<?php

namespace PDPhilip\Elasticsearch\Query;


use PDPhilip\Elasticsearch\Connection;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use PDPhilip\Elasticsearch\Schema\Schema;
use RuntimeException;
use LogicException;

class Builder extends BaseBuilder
{

    protected $index;

    protected $refresh = 'wait_for';

    public $options = [];

    public $paginating = false;


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
        'exist',
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

    /**
     * @inheritdoc
     */
    public function chunkById($count, callable $callback, $column = '_id', $alias = null)
    {
        return parent::chunkById($count, $callback, $column, $alias);
    }

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

        if ($this->groups) {
            throw new RuntimeException('Group By is not available yet');
        }

        if ($this->aggregate) {
            $function = $this->aggregate['function'];

            $totalResults = $this->connection->aggregate($function, $wheres, $options, $columns);
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
            throw new RuntimeException('Distinct is not available yet');
        }

        //Else Normal find query

        $find = $this->connection->find($wheres, $options, $columns);
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
    public function orderBy($column, $direction = 'asc')
    {
        if (is_string($direction)) {
            $direction = (strtolower($direction) == 'asc' ? 1 : -1);
        }

        $this->orders[$column] = $direction;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $type = 'between';

        $this->wheres[] = compact('column', 'type', 'boolean', 'values', 'not');

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function newQuery()
    {
        return new self($this->connection, $this->processor);
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

        if ($operator === 'not like') {
            $operator = 'not_like';
        }

        if (!isset($operator) || $operator == '=') {
            $query = [$column => $value];
        } elseif (array_key_exists($operator, $this->conversion)) {
            $query = [$column => [$this->conversion[$operator] => $value]];
        } else {
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
        $query = $where['query'];

        return $query->compileWheres();
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
        $where['operator'] = '=';
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
        $where['operator'] = 'ne';
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
        //Just a normal where query.....
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

//        return $this->_parseWhereBasic($where);
    }

    /**
     * @param    array    $where
     *
     * @return array
     */
    protected function _parseWhereDay(array $where)
    {
        throw new LogicException('whereDay clause is not available yet');

        return $this->_parseWhereBasic($where);
    }

    /**
     * @param    array    $where
     *
     * @return array
     */
    protected function _parseWhereYear(array $where)
    {
        throw new LogicException('whereYear clause is not available yet');

        return $this->_parseWhereBasic($where);
    }

    /**
     * @param    array    $where
     *
     * @return array
     */
    protected function _parseWhereTime(array $where)
    {
        throw new LogicException('whereTime clause is not available yet');

        return $this->_parseWhereBasic($where);
    }

    /**
     * @param    array    $where
     *
     * @return mixed
     */
    protected function _parseWhereRaw(array $where)
    {
        return $where['sql'];
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

    public function rawSearch(array $bodyParams)
    {
        $find = $this->connection->searchRaw($bodyParams);
        $data = $find->data;

        return new Collection($data);
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
    public function distinct($column = false)
    {
        throw new LogicException('distinct() is currently not supported');
    }

    /**
     * @inheritdoc
     */
    public function groupBy(...$groups)
    {
        throw new LogicException('groupBy() is currently not supported');
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
    // WIP::
    //----------------------------------------------------------------------

    public function search($text, $columns = [], $returnLazy = false)
    {
        throw new LogicException('Search is coming');
        $options = $this->compileOptions();
        $result = $this->connection->search($text, $options, $columns);

        if ($result->isSuccessful()) {
            $data = $result->data;
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
            throw new RuntimeException('Error: '.$result->errorMessage);
        }
    }


}

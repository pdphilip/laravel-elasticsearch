<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Data\Meta;
use PDPhilip\Elasticsearch\Traits\HasOptions;
use PDPhilip\Elasticsearch\Traits\Query\ManagesParameters;

/**
 * @property Connection $connection
 * @property Processor $processor
 * @property Grammar $grammar
 */
class Builder extends BaseBuilder
{
    use HasOptions;
    use ManagesParameters;

    /** @var string[] */
    public const CONFLICT = [
        'ABORT' => 'abort',
        'PROCEED' => 'proceed',
    ];

    public $bucketAggregations;

    public $metricsAggregations;

    public $distinct;

    public $filters;

    public $includeInnerHits;

    /**
     * {@inheritdoc}
     */
    public $limit = 1000;

    /**
     * All the supported clause operators.
     *
     * @var array
     */
    public $operators = ['=', '<', '>', '<=', '>=', '<>', '!=', 'exists', 'like', 'not like'];

    public $postFilters;

    public $scripts = [];

    public $type;

    protected array $mapping = [];

    protected $parentId;

    protected $rawResponse;

    protected $results;

    /** @var int */
    protected $resultsOffset;

    protected $routing;

    public function __call($method, $parameters)
    {
        if (Str::startsWith($method, 'filterWhere')) {
            return $this->dynamicFilter($method, $parameters);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Add a filter query by calling the required 'where' method
     * and capturing the added where as a filter
     */
    public function dynamicFilter(string $method, array $args): self
    {
        $method = ucfirst(substr($method, 6));

        $numWheres = count($this->wheres);

        $this->$method(...$args);

        $filterType = array_pop($args) === 'postFilter' ? 'postFilters' : 'filters';

        if (count($this->wheres) > $numWheres) {
            $this->$filterType[] = array_pop($this->wheres);
        }

        return $this;
    }

    /**
     * Add another query builder as a nested where to the query builder.
     *
     * @param  \Illuminate\Database\Query\Builder|static  $query
     * @param  string  $boolean
     */
    public function addNestedWhereQuery($query, $boolean = 'and'): self
    {
        $type = 'Nested';

        $compiled = compact('type', 'query', 'boolean');

        if (count($query->wheres)) {
            $this->wheres[] = $compiled;
        }

        if (isset($query->filters) && count($query->filters)) {
            $this->filters[] = $compiled;
        }

        return $this;
    }

    /**
     * Add a text search clause to the query.
     *
     * @param  string  $query
     * @param  array  $options
     * @param  string  $boolean
     */
    public function search($query, $options = [], $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'Search',
            'value' => $query,
            'boolean' => $boolean,
            'options' => $options,
        ];

        return $this;
    }

    /**
     * Get a generator for the given query.
     *
     * @return \Generator
     */
    public function cursor()
    {
        if (is_null($this->columns)) {
            $this->columns = ['*'];
        }

        foreach ($this->connection->cursor($this->toCompiledQuery()) as $document) {
            yield $this->processor->documentFromResult($this, $document);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decrementEach(array $columns, array $extra = [])
    {
        return $this->buildCrementEach($columns, 'decrement', $extra);
    }

    /**
     * Force the query to only return distinct results.
     *
     * @return $this
     */
    public function distinct()
    {
        $columns = func_get_args();

        if (count($columns) > 0) {
            $this->distinct = is_array($columns[0]) ? $columns[0] : $columns;
        } else {
            $this->distinct = [];
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function groupBy(...$groups)
    {
        $this->bucketAggregation('group_by', 'composite', function (Builder $query) use ($groups) {
            $query->from = $this->from;

            return collect($groups)->map(function ($group) use ($query) {
                return [$group => ['terms' => ['field' => $query->grammar->getIndexableField($group, $query)]]];
            })->toArray();
        });

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function exists()
    {
        $this->applyBeforeQueryCallbacks();

        $results = $this->processor->processSelect($this, $this->connection->select(
            $this->grammar->compileExists($this), $this->getBindings(), ! $this->useWritePdo
        ));

        // If the results have rows, we will get the row and see if the exists column is a
        // boolean true. If there are no results for this query we will return false as
        // there are no rows for this query at all, and we can return that info here.
        if (isset($results[0])) {
            $results = (array) $results[0];

            return (bool) $results['_id'];
        }

        return false;
    }

    /**
     * Adds a function score of any type
     *
     * @param  array  $options  see elastic search docs for options
     * @param  string  $boolean
     */
    public function functionScore($functionType, $options = [], $boolean = 'and'): self
    {
        $where = [
            'type' => 'FunctionScore',
            'function_type' => $functionType,
            'boolean' => $boolean,
        ];

        $this->wheres[] = array_merge($where, $options);

        return $this;
    }

    /**
     * Get the count of the total records for the paginator.
     *
     * @param  array  $columns
     * @return int
     */
    public function getCountForPagination($columns = ['*'])
    {
        if ($this->results === null) {
            $this->runPaginationCountQuery();
        }

        $total = $this->processor->getRawResponse()['hits']['total'];

        return is_array($total) ? $total['value'] : $total;
    }

    /**
     * Run a pagination count query.
     *
     * @param  array  $columns
     * @return array
     */
    protected function runPaginationCountQuery($columns = ['_id'])
    {
        return $this->cloneWithout(['columns', 'orders', 'limit', 'offset'])
            ->limit(1)
            ->get($columns)->all();
    }

    /**
     * Get mappings without re-fetching for subsequent calls.
     *
     * @return array
     */
    public function getMapping()
    {
        if (empty($this->mapping)) {
            $this->mapping = $this->connection->indices()->getMapping($this->grammar->compileIndexMappings($this))->asArray();
        }

        return $this->mapping;
    }

    /**
     * @return mixed|null
     */
    public function getOption(string $option, mixed $default = null)
    {
        return $this->options()->get($option, $default);
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Support\Collection
     */
    public function get($columns = ['*'])
    {
        $original = $this->columns;

        if (is_null($original)) {
            $this->columns = $columns;
        }

        $results = $this->getResultsOnce();

        $this->columns = $original;

        return collect($results);
    }

    /**
     * Get results without re-fetching for subsequent calls.
     *
     * @return array
     */
    protected function getResultsOnce()
    {
        if (! $this->hasProcessedSelect()) {
            $this->results = $this->processor->processSelect($this, $this->runSelect());
        }

        $this->resultsOffset = $this->offset;

        return $this->results;
    }

    protected function hasProcessedSelect(): bool
    {
        if ($this->results === null) {
            return false;
        }

        return $this->offset === $this->resultsOffset;
    }

    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return iterable
     */
    protected function runSelect()
    {
        return $this->connection->select($this->toCompiledQuery());
    }

    /**
     * Get the Elasticsearch representation of the query.
     */
    public function toCompiledQuery(): array
    {
        return $this->toSql();
    }

    /**
     * Get the parent ID to be used when routing queries to Elasticsearch
     */
    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getRouting(): ?string
    {
        return $this->routing;
    }

    /**
     * {@inheritdoc}
     */
    public function incrementEach(array $columns, array $extra = [])
    {
        return $this->buildCrementEach($columns, 'increment', $extra);
    }

    /**
     * Build and add increment or decrement scripts for the given columns.
     *
     * @param  array  $columns  Associative array of columns and their corresponding increment/decrement amounts.
     * @param  string  $type  Type of operation, either 'increment' or 'decrement'.
     * @param  array  $extra  Additional options for the update.
     * @return mixed The result of the update operation.
     *
     * @throws InvalidArgumentException If a non-numeric value is passed as an increment amount
     *                                  or a non-associative array is passed to the method.
     */
    private function buildCrementEach(array $columns, string $type, array $extra = [])
    {
        foreach ($columns as $column => $amount) {
            if (! is_numeric($amount)) {
                throw new InvalidArgumentException("Non-numeric value passed as increment amount for column: '$column'.");
            } elseif (! is_string($column)) {
                throw new InvalidArgumentException('Non-associative array passed to incrementEach method.');
            }

            $operator = $type == 'increment' ? '+' : '-';

            $script = implode('', [
                "if (ctx._source.{$column} == null) { ctx._source.{$column} = 0; }",
                "ctx._source.{$column} $operator= params.{$type}_{$column}_value;",
            ]);

            $options['params'] = ["{$type}_{$column}_value" => (int) $amount];

            $this->scripts[] = compact('script', 'options');
        }

        if (empty($this->wheres)) {
            $this->wheres[] = [
                'type' => 'MatchAll',
                'boolean' => 'and',
            ];
        }

        return $this->update($extra);
    }

    /**
     * {@inheritdoc}
     */
    public function insert(array $values): Meta|bool
    {
        // Since every insert gets treated like a batch insert, we will have to detect
        // if the user is inserting a single document or an array of documents.
        $batch = true;

        foreach ($values as $value) {
            // As soon as we find a value that is not an array we assume the user is
            // inserting a single document.
            if (! is_array($value)) {
                $batch = false;
                break;
            }
        }

        if (! $batch) {
            $values = [$values];
        }

        return $this->processor->processInsert($this, $this->connection->insert($this->grammar->compileInsert($this, $values)));
    }

    /**
     * Set how to handle conflicts during a delete request
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-delete-by-query.html#docs-delete-by-query-api-query-params
     *
     * @throws \Exception
     */
    public function onConflicts(string $option = self::CONFLICT['ABORT']): self
    {
        if (in_array($option, self::CONFLICT)) {
            $this->options['delete_conflicts'] = $option;

            return $this;
        }

        throw new \Exception(
            "$option is an invalid conflict option, valid options are: ".implode(', ', self::CONFLICT)
        );
    }

    /**
     * Add an "or where weekday" statement to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  \DateTimeInterface|string  $value
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function orWhereWeekday($column, $operator, $value = null)
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        return $this->addDateBasedWhere('Weekday', $column, $operator, $value, 'or');
    }

    /**
     * @param  string  $column
     * @param  int  $direction
     */
    public function orderBy($column, $direction = 1, array $options = []): self
    {
        if (is_string($direction)) {
            $direction = strtolower($direction) == 'asc' ? 1 : -1;
        }

        $type = isset($options['type']) ? $options['type'] : 'basic';

        $this->orders[] = compact('column', 'direction', 'type', 'options');

        return $this;
    }

    public function orderByGeo(string $column, array $coordinates, int $direction = 1, array $options = []): self
    {

        $options = [
            ...$options,
            'type' => 'geoDistance',
            'coordinates' => $coordinates,
        ];

        return $this->orderBy($column, $direction, $options);
    }

    public function orderByGeoDesc(string $column, array $coordinates, array $options = []): self
    {

        $options = [
            ...$options,
            'type' => 'geoDistance',
            'coordinates' => $coordinates,
        ];

        return $this->orderBy($column, -1, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function orderByDesc($column, array $options = [])
    {
        return $this->orderBy($column, 'desc', $options);
    }

    /**
     * Set the parent ID to be used when routing queries to Elasticsearch
     */
    public function parentId(string $id): self
    {
        $this->parentId = $id;

        return $this;
    }

    /**
     * Remove one or more values from an array.
     *
     * @param  string|array  $column
     * @param  mixed  $value
     * @return int
     */
    public function pull($column, $value = null)
    {
        $value = is_array($value) ? $value : [$value];

        // Prepare the script for pulling/removing values.
        $script = "
        if (ctx._source.{$column} != null) {
            ctx._source.{$column}.removeIf(item -> {
                for (removeItem in params.pull_values) {
                    if (item == removeItem) {
                        return true;
                  }
                }
                return false;
            });
        }
    ";

        $options['params'] = ['pull_values' => $value];
        $this->scripts[] = compact('script', 'options');

        return $this->update([]);
    }

    /**
     * Append one or more values to an array.
     *
     * @param  string|array  $column
     * @param  mixed  $value
     * @param  bool  $unique
     * @return int
     */
    public function push($column, $value = null, $unique = false)
    {
        // Check if we are pushing multiple values.
        $batch = is_array($value) && array_is_list($value);

        $value = $batch ? $value : [$value];

        // Prepare the script for unique or non-unique addition.
        if ($unique) {
            $script = "
            if (ctx._source.{$column} == null) {
                ctx._source.{$column} = [];
            }
            for (item in params.push_values) {
                if (!ctx._source.{$column}.contains(item)) {
                    ctx._source.{$column}.add(item);
                }
            }
        ";
        } else {
            $script = "
            if (ctx._source.{$column} == null) {
                ctx._source.{$column} = [];
            }
            ctx._source.{$column}.addAll(params.push_values);
        ";
        }

        $options['params'] = ['push_values' => $value];
        $this->scripts[] = compact('script', 'options');

        return $this->update([]);
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $values)
    {
        return $this->processor->processUpdate($this, parent::update($values));
    }

    public function routing(string $routing): self
    {
        $this->routing = $routing;

        return $this;
    }

    /**
     * Set the suffix that is appended to from.
     */
    public function suffix(): string
    {
        return $this->options()->get('suffix', '');
    }

    public function truncate()
    {
        $this->applyBeforeQueryCallbacks();

        return $this->processor->processDelete($this, $this->connection->delete($this->grammar->compileTruncate($this)));
    }

    /**
     * {@inheritdoc}
     */
    public function delete($id = null)
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from this
        // database without manually specifying the "where" clauses on the query.
        if (! is_null($id)) {
            $this->where('id', '=', $id);
        }

        return $this->processor->processDelete($this, $this->connection->delete($this->grammar->compileDelete($this)));
    }

    /**
     * Set the document type the search is targeting.
     *
     * @param  string  $type
     */
    public function type($type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @param  bool  $not
     */
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false): self
    {
        $type = 'Between';

        $this->wheres[] = compact('column', 'values', 'type', 'boolean', 'not');

        return $this;
    }

    /**
     * Add a where child statement to the query.
     *
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereChild(
        string $documentType,
        Closure $callback,
        array $options = [],
        string $boolean = 'and'
    ): self {
        return $this->whereRelationship('child', $documentType, $callback, $options, $boolean);
    }

    /**
     * Add a "where date" statement to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereDate($column, $operator, $value = null, $boolean = 'and', $not = false): self
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() == 2
        );

        $type = 'Date';

        $this->wheres[] = compact('column', 'operator', 'value', 'type', 'boolean', 'not');

        return $this;
    }

    /**
     * Add a 'distance from point' statement to the query.
     *
     * @param  string  $column
     */
    public function whereGeoBoundsIn($column, array $bounds): self
    {
        $type = 'GeoBoundsIn';

        $this->wheres[] = [
            'column' => $column,
            'bounds' => $bounds,
            'type' => 'GeoBoundsIn',
            'boolean' => 'and',
            'not' => false,
        ];

        return $this;
    }

    /**
     * Add a 'distance from point' statement to the query.
     *
     * @param  string  $column
     * @param  array  $coords
     * @param  string  $boolean
     */
    public function whereGeoDistance($column, array $location, string $distance, $boolean = 'and', bool $not = false): self
    {
        $type = 'GeoDistance';

        $this->wheres[] = compact('column', 'location', 'distance', 'type', 'boolean', 'not');

        return $this;
    }

    /**
     * Add a 'nested document' statement to the query.
     *
     * @param  string  $column
     * @param  callable|\Illuminate\Database\Query\Builder|static  $query
     * @param  string  $boolean
     */
    public function whereNestedDoc($column, $query, $boolean = 'and'): self
    {
        $type = 'NestedDoc';

        if (! is_string($query) && is_callable($query)) {
            call_user_func($query, $query = $this->newQuery());
        }

        $this->wheres[] = compact('column', 'query', 'type', 'boolean');

        return $this;
    }

    /**
     * Add a 'must not' statement to the query.
     *
     * @param  \Illuminate\Database\Query\Builder|static  $query
     * @param  string  $boolean
     */
    public function whereNot($query, $operator = null, $value = null, $boolean = 'and'): self
    {
        $type = 'Not';

        call_user_func($query, $query = $this->newQuery());

        $this->wheres[] = compact('query', 'type', 'boolean');

        return $this;
    }

    /**
     * Add a where parent statement to the query.
     *
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereParent(
        string $documentType,
        Closure $callback,
        array $options = [],
        string $boolean = 'and'
    ): self {
        return $this->whereRelationship('parent', $documentType, $callback, $options, $boolean);
    }

    /**
     * Add a where relationship statement to the query.
     *
     *
     * @return \Illuminate\Database\Query\Builder|static
     */
    protected function whereRelationship(
        string $relationshipType,
        string $documentType,
        Closure $callback,
        array $options = [],
        string $boolean = 'and'
    ): self {
        call_user_func($callback, $query = $this->newQuery());

        $this->wheres[] = [
            'type' => ucfirst($relationshipType),
            'documentType' => $documentType,
            'value' => $query,
            'options' => $options,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * @param  string  $key
     * @param  string  $type
     * @param  null  $args
     * @param  null  $aggregations
     */
    public function bucketAggregation($key, $type = null, $args = null, $aggregations = null): self
    {

        if (! is_string($args) && is_callable($args)) {
            $args = call_user_func($args, $this->newQuery());
        }

        if (! is_string($aggregations) && is_callable($aggregations)) {
            $aggregations = call_user_func($aggregations, $this->newQuery());
        }

        $this->bucketAggregations[] = compact(
            'key',
            'type',
            'args',
            'aggregations'
        );

        return $this;
    }

    /**
     * @param  string  $parentType  Name of the parent relation from the join mapping
     * @param  mixed  $id
     * @return QueryBuilder
     */
    public function whereParentId(string $parentType, $id, string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'ParentId',
            'parentType' => $parentType,
            'id' => $id,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add a 'regexp' statement to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     */
    public function whereRegex($column, string $value, $boolean = 'and', bool $not = false, array $parameters = []): self
    {
        $type = 'Regex';

        $this->wheres[] = compact('column', 'value', 'type', 'boolean', 'not', 'parameters');

        return $this;
    }

    /**
     * Add a script query
     *
     * @param  string  $boolean
     */
    public function whereScript(string $script, array $options = [], $boolean = 'and'): self
    {
        $type = 'Script';

        $this->wheres[] = compact('script', 'options', 'type', 'boolean');

        return $this;
    }

    /**
     * Add a prefix query
     *
     * @param  string  $column
     * @param  string  $boolean
     * @param  bool  $not
     */
    public function whereStartsWith($column, string $value, $boolean = 'and', $not = false): self
    {
        $type = 'Prefix';

        $this->wheres[] = compact('column', 'value', 'type', 'boolean', 'not');

        return $this;
    }

    /**
     * Add a "where weekday" statement to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  \DateTimeInterface|string  $value
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereWeekday($column, $operator, $value = null, $boolean = 'and')
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('N');
        }

        return $this->addDateBasedWhere('Weekday', $column, $operator, $value, $boolean);
    }

    /**
     * Add a date based (year, month, day, time) statement to the query.
     *
     * @param  string  $type
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    protected function addDateBasedWhere($type, $column, $operator, $value, $boolean = 'and')
    {
        switch ($type) {
            case 'Year':
                $dateType = 'year';
                break;

            case 'Month':
                $dateType = 'month.value';
                break;

            case 'Day':
                $dateType = 'dayOfMonth';
                break;

            case 'Weekday':
                $dateType = 'dayOfWeekEnum.value';
                break;
        }

        $type = 'Script';

        $operator = $operator == '=' ? '==' : $operator;
        $operator = $operator == '<>' ? '!=' : $operator;

        $script = "doc.{$column}.size() > 0 && doc.{$column}.value != null && doc.{$column}.value.{$dateType} {$operator} params.value";

        $options['params'] = ['value' => (int) $value];

        $this->wheres[] = compact('script', 'options', 'type', 'boolean');

        return $this;
    }

    /**
     * Add any where clause with given options.
     */
    public function whereWithOptions(...$args): self
    {
        $options = array_pop($args);
        $type = array_shift($args);
        $method = $type == 'Basic' ? 'where' : 'where'.$type;

        $this->$method(...$args);

        $this->wheres[count($this->wheres) - 1]['options'] = $options;

        return $this;
    }

    /**
     * Whether to include inner hits in the response
     */
    public function withInnerHits(): self
    {
        $this->includeInnerHits = true;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function avg($column, array $options = [])
    {
        return $this->aggregate(__FUNCTION__, Arr::wrap($column), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function count($columns = '*', array $options = [])
    {
        return $this->aggregate(__FUNCTION__, Arr::wrap($columns), $options);
    }

    /**
     * {@inheritdoc}
     *
     * @param  Expression|string|array  $columns
     */
    public function min($column, array $options = [])
    {
        return $this->aggregate(__FUNCTION__, Arr::wrap($column), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function max($column, array $options = [])
    {
        return $this->aggregate(__FUNCTION__, Arr::wrap($column), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function sum($column, array $options = [])
    {
        $result = $this->aggregate(__FUNCTION__, Arr::wrap($column), $options);

        return $result ?: 0;
    }

    /**
     * Retrieve the stats of the values of a given column.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-stats-aggregation.html
     *
     * @param  Expression|string|array  $columns
     * @param  array  $options
     */
    public function stats($columns, $options = [])
    {
        $result = $this->aggregate(__FUNCTION__, Arr::wrap($columns), $options);

        return $result ?: [];
    }

    /**
     * Retrieve the extended stats of the values of a given column.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-extendedstats-aggregation.html
     *
     * @param  Expression|string|array  $columns
     * @param  array  $options
     */
    public function extendedStats($columns, $options = [])
    {
        $result = $this->aggregate('extended_stats', Arr::wrap($columns), $options);

        return $result ?: [];
    }

    /**
     * Retrieve the median absolute deviation stats of the values of a given column.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-median-absolute-deviation-aggregation.html
     *
     * @param  Expression|string|array  $columns
     * @param  array  $options
     */
    public function medianAbsoluteDeviation($columns, $options = [])
    {
        $result = $this->aggregate('median_absolute_deviation', Arr::wrap($columns), $options);

        return $result ?: [];
    }

    /**
     * Retrieve the percentiles of the values of a given column.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-percentile-aggregation.html
     *
     * @param  Expression|string|array  $columns
     * @param  array  $options
     */
    public function percentiles($columns, $options = [])
    {
        $result = $this->aggregate('percentiles', Arr::wrap($columns), $options);

        return $result ?: [];
    }

    /**
     * Retrieve the String Stats of the values of a given keyword column.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-string-stats-aggregation.html
     *
     * @param  Expression|string|array  $columns
     * @param  array  $options
     */
    public function stringStats($columns, $options = [])
    {
        $result = $this->aggregate('string_stats', Arr::wrap($columns), $options);

        return $result ?: [];
    }

    /**
     * Retrieve the String Stats of the values of a given keyword column.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-string-stats-aggregation.html
     *
     * @param  Expression|string|array  $columns
     * @param  array  $options
     */
    public function matrix($columns, $options = [])
    {
        $result = $this->aggregate('matrix_stats', Arr::wrap($columns), $options);

        return $result ?: [];
    }

    /**
     * A boxplot metrics aggregation that computes boxplot of numeric values extracted from the aggregated documents.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-boxplot-aggregation.html
     *
     * @param  Expression|string|array  $columns
     * @param  array  $options
     */
    public function boxplot($columns, $options = [])
    {
        $result = $this->aggregate('boxplot', Arr::wrap($columns), $options);

        return $result ?: [];
    }

    /**
     * Retrieve the Cardinality Stats of the values of a given keyword column.
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-cardinality-aggregation.html
     *
     * @param  Expression|string|array  $columns
     * @param  array  $options
     */
    public function cardinality($columns, $options = [])
    {
        $result = $this->aggregate(__FUNCTION__, Arr::wrap($columns), $options);

        return $result ?: [];
    }

    public function aggregate($function, $columns = ['*'], $options = [])
    {
        return $this->aggregateMetric($function, $columns, $options);
    }

    public function aggregateMetric($function, $columns = ['*'], $options = [])
    {

        //Each column we want aggregated
        $columns = Arr::wrap($columns);
        foreach ($columns as $column) {
            $this->metricsAggregations[] = [
                'key' => $column,
                'args' => $column,
                'type' => $function,
                'options' => $options,
            ];
        }

        return $this->processor->processAggregations($this, $this->connection->select($this->grammar->compileSelect($this), []));
    }
}

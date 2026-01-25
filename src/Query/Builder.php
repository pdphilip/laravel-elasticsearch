<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query;

use Carbon\Carbon;
use Closure;
use DateTimeInterface;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Exception;
use Generator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Data\MetaDTO;
use PDPhilip\Elasticsearch\Eloquent\ElasticCollection;
use PDPhilip\Elasticsearch\Exceptions\LogicException;
use PDPhilip\Elasticsearch\Exceptions\RuntimeException;
use PDPhilip\Elasticsearch\Query\Concerns\BuildsAggregations;
use PDPhilip\Elasticsearch\Query\Concerns\BuildsSearchQueries;
use PDPhilip\Elasticsearch\Query\Concerns\HandlesScripts;
use PDPhilip\Elasticsearch\Query\Concerns\ManagesPit;
use PDPhilip\Elasticsearch\Schema\Schema;
use PDPhilip\Elasticsearch\Traits\HasOptions;
use PDPhilip\Elasticsearch\Utils\Sanitizer;

/**
 * @method $this save()
 *
 * Filter methods - apply filters that don't affect scoring.
 * Pass 'postFilter' as final argument for faceted search filtering.
 * @method $this filterWhere(string $column, mixed $operator = null, mixed $value = null)
 * @method $this filterWhereIn(string $column, array $values)
 * @method $this filterWhereNotIn(string $column, array $values)
 * @method $this filterWhereBetween(string $column, array $values)
 * @method $this filterWhereNull(string $column)
 * @method $this filterWhereNotNull(string $column)
 * @method $this filterWhereDate(string $column, mixed $operator, mixed $value = null)
 * @method $this filterWhereTerm(string $column, mixed $value)
 * @method $this filterWhereMatch(string $column, mixed $value)
 * @method $this filterWherePhrase(string $column, mixed $value)
 * @method $this filterWhereFuzzy(string $column, mixed $value)
 * @method $this filterWhereRegex(string $column, string $pattern)
 * @method $this filterWhereGeoDistance(string $field, string $distance, array $location)
 * @method $this filterWhereGeoBox(string $field, array $topLeft, array $bottomRight)
 * @method $this filterWhereNestedObject(string $column, callable $query)
 *
 * @property Connection $connection
 * @property Processor $processor
 * @property Grammar $grammar
 */
class Builder extends BaseBuilder
{
    use BuildsAggregations;
    use BuildsSearchQueries;
    use HandlesScripts;
    use HasOptions;
    use ManagesOptions;
    use ManagesPit;

    /** @var string[] */
    public const CONFLICT = [
        'ABORT' => 'abort',
        'PROCEED' => 'proceed',
    ];

    public array $bucketAggregations = [];

    public $distinct = false;

    public $bulkDistinct = false;

    public $distinctCount = false;

    public $filters;

    public $highlight;

    /** @var bool */
    public $includeInnerHits;

    /**
     * {@inheritdoc}
     */
    //    public $limit = ;

    public array $sorts = [];

    public array $bodyParameters = [];

    public mixed $pitId = null;

    public string $keepAlive = '1m';

    public mixed $searchAfter = null;

    public mixed $after = null;

    public array $metricsAggregations = [];

    public mixed $asDsl = false;

    /**
     * All the supported clause operators.
     *
     * @var array
     */
    public $operators = ['=', '<', '>', '<=', '>=', '<>', '!=', 'exists', 'like', 'not like'];

    public array $postFilters = [];

    public $scripts = [];

    public $type;

    protected array $mapping = [];

    protected $parentId;

    protected $results;

    /** @var int */
    protected $resultsOffset;

    protected $routing;

    public $cursorMeta = [];

    protected ?MetaDTO $metaTransfer = null;

    public function __construct(...$args)
    {
        parent::__construct(...$args);
        $this->applyConnectionOptions();
    }

    public function __call($method, $parameters)
    {
        if (Str::startsWith($method, 'filterWhere')) {
            return $this->dynamicFilter($method, $parameters);
        }

        return parent::__call($method, $parameters);
    }

    public function toDsl(): array|string
    {
        $this->applyBeforeQueryCallbacks();

        return $this->grammar->compileSelect($this);
    }

    public function toSql(): array|string
    {
        return $this->toDsl();
    }

    private function applyConnectionOptions()
    {
        $trackTotalHits = $this->connection->options()->get('track_total_hits');
        if ($trackTotalHits !== null) {
            $this->bodyParameters['track_total_hits'] = $trackTotalHits;
        }
    }

    // ======================================================================
    // Inherited Methods
    // ======================================================================

    /**
     * Force the query to only return distinct results.
     */
    public function distinct(mixed $columns = [], bool $includeCount = false)
    {
        $original = $this->columns ?? [];

        $withCount = $includeCount;
        if (is_bool($columns)) {
            $withCount = $columns;
        } elseif ($columns) {
            $columns = Arr::wrap($columns);
            $this->columns = array_merge($original, $columns);
        }
        $this->distinctCount = $withCount;
        $this->distinct = true;

        return $this->get();
    }

    public function bulkDistinct(mixed $columns = [], bool $includeCount = false)
    {
        $original = $this->columns ?? [];
        $withCount = $includeCount;
        if (is_bool($columns)) {
            $withCount = $columns;
        } elseif ($columns) {
            $columns = Arr::wrap($columns);
            $this->columns = array_merge($original, $columns);
        }

        $this->distinctCount = $withCount;
        $this->bulkDistinct = true;

        return $this->get();

    }

    /**
     * {@inheritdoc}
     *
     *  Match query: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-query.html
     *  or Range query: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-query.html
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and', $options = [])
    {

        [$column, $operator, $value, $boolean, $options] = $this->extractOptionsWithOperator('Where', $column, $operator, $value, $boolean, $options);
        parent::where($column, $operator, $value, $boolean)->applyOptions($options);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function orWhere($column, $operator = null, $value = null, $options = []): self
    {
        return $this->where($column, $operator, $value, 'or', $options);
    }

    /**
     * {@inheritdoc}
     */
    public function whereNot($column, $operator = null, $value = null, $boolean = 'and', $options = []): self
    {
        [$column, $operator, $value, $boolean, $options] = $this->extractOptionsWithOperator('Where', $column, $operator, $value, $boolean, $options);

        return parent::whereNot($column, $operator, $value, $boolean)->applyOptions($options);
    }

    /**
     * {@inheritdoc}
     */
    public function orWhereNot($column, $operator = null, $value = null, $options = []): self
    {
        return $this->whereNot($column, $operator, $value, 'or', $options);
    }

    /**
     * {@inheritdoc}
     */
    public function whereRaw($sql, $bindings = [], $boolean = 'and', $options = []): self
    {
        parent::whereRaw($sql, $bindings, $boolean);
        // Append options to clause
        $this->applyOptions($options);

        return $this;
    }

    /**
     * {@inheritdoc}
     * Terms query: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-terms-query.html
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false, $options = [])
    {
        [$column, $values, $not, $boolean, $options] = $this->extractOptionsWithNot('Term', $column, $values, $boolean, $not, $options);
        $type = 'In';
        if ($not) {
            $boolean .= ' not';
        }

        // @inheritdoc
        if ($this->isQueryable($values)) {
            [$query, $bindings] = $this->createSub($values);
            $values = [new Expression($query)];
            $this->addBinding($bindings, 'where');
        }

        // @inheritdoc
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        if (count($values) !== count(Arr::flatten($values, 1))) {
            throw new InvalidArgumentException('Nested arrays may not be passed to whereIn method.');
        }

        // @inheritdoc
        $this->addBinding($this->cleanBindings($values), 'where');
        $this->applyOptions($options);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function orWhereIn($column, $values, $options = [])
    {
        return $this->whereIn($column, $values, 'or', false, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function whereNotIn($column, $values, $boolean = 'and', $options = [])
    {
        return $this->whereIn($column, $values, $boolean, true, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function orWhereNotIn($column, $values, $options = [])
    {
        return $this->whereIn($column, $values, 'or', true, $options);
    }

    public function whereNull($columns, $boolean = 'and', $not = false)
    {
        // whereNull == Not Exists
        $notExist = ! $not;
        $type = 'Exists';
        $wasNot = str_ends_with($boolean, 'not');
        if ($wasNot) {
            $notExist = ! $notExist;
        }
        $boolParts = explode(' ', $boolean);
        $boolean = $boolParts[0];

        if ($notExist) {
            $boolean .= ' not';
        }

        foreach (Arr::wrap($columns) as $column) {
            $this->wheres[] = compact('type', 'column', 'boolean');
        }

        return $this;
    }

    public function orWhereNull($column)
    {

        return $this->whereNull($column, 'or');
    }

    public function whereNotNull($columns, $boolean = 'and')
    {
        return $this->whereNull($columns, $boolean, true);
    }

    public function orWhereNotNull($column)
    {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * Add a where between statement to the query.
     * Range query: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-query.html
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @param  bool  $not
     */
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false, $options = []): self
    {
        [$column, $values, $not, $boolean, $options] = $this->extractOptionsWithNot('Where', $column, $values, $boolean, $not, $options);
        $type = 'Between';
        $this->wheres[] = compact('column', 'values', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function orWhereBetween($column, iterable $values, $options = [])
    {
        return $this->whereBetween($column, $values, 'or', false, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function whereNotBetween($column, iterable $values, $boolean = 'and', $options = [])
    {
        return $this->whereBetween($column, $values, $boolean, true, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function orWhereNotBetween($column, iterable $values, $options = [])
    {
        return $this->whereBetween($column, $values, 'or', true, $options);
    }

    /**
     * Add a "where date" statement to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     */
    public function whereDate($column, $operator, $value = null, $boolean = 'and', array $options = []): self
    {
        [$column, $operator, $value, $boolean, $options] = $this->extractOptionsWithOperator('Date', $column, $operator, $value, $boolean, $options);
        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() == 2
        );
        $type = 'Date';

        $this->wheres[] = compact('column', 'operator', 'value', 'type', 'boolean', 'options');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function orWhereDate($column, $operator, $value = null, array $options = [])
    {
        return $this->whereDate($column, $operator, $value, 'or', $options);
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
    protected function addDateBasedWhere($type, $column, $operator, $value, $boolean = 'and', array $options = [])
    {
        // Handle Time type specially since it compares multiple components
        if ($type === 'Time') {
            return $this->addTimeBasedWhere($column, $operator, $value, $boolean, $options);
        }

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

            default:
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
     * Add a time-based where clause (whereTime) to the query.
     *
     * Handles time comparisons with various formats: HH:mm:ss, HH:mm, or HH
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  string  $value
     * @param  string  $boolean
     * @return $this
     */
    protected function addTimeBasedWhere($column, $operator, $value, $boolean = 'and', array $options = [])
    {
        $type = 'Script';

        $operator = $operator == '=' ? '==' : $operator;
        $operator = $operator == '<>' ? '!=' : $operator;

        // Parse time value - can be HH:mm:ss, HH:mm, or just HH
        $timeParts = explode(':', $value);
        $hour = (int) ($timeParts[0] ?? 0);
        $minute = isset($timeParts[1]) ? (int) $timeParts[1] : null;
        $second = isset($timeParts[2]) ? (int) $timeParts[2] : null;

        // Build time comparison - convert to seconds since midnight for easier comparison
        $docTimeExpr = "doc.{$column}.value.hour * 3600 + doc.{$column}.value.minute * 60 + doc.{$column}.value.second";

        // Build the target time value in seconds since midnight
        $targetSeconds = $hour * 3600;
        if ($minute !== null) {
            $targetSeconds += $minute * 60;
            if ($second !== null) {
                $targetSeconds += $second;
            }
        }

        // For partial time matches (HH:mm or just HH), adjust the comparison
        if ($second === null && $minute === null) {
            // Just hour specified - compare only the hour part
            $docTimeExpr = "doc.{$column}.value.hour";
            $targetSeconds = $hour;
        } elseif ($second === null) {
            // Hour and minute specified - compare hour*60+minute
            $docTimeExpr = "doc.{$column}.value.hour * 60 + doc.{$column}.value.minute";
            $targetSeconds = $hour * 60 + $minute;
        }

        $script = "doc.{$column}.size() > 0 && doc.{$column}.value != null && {$docTimeExpr} {$operator} params.value";

        $options['params'] = ['value' => $targetSeconds];

        $this->wheres[] = compact('script', 'options', 'type', 'boolean');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function orderByDesc($column, array $options = [])
    {
        return $this->orderBy($column, 'desc', $options);
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
        if (in_array($column, ['_count'])) {
            $this->sorts[$column] = $direction;

            return $this;
        } elseif (is_array($direction)) {
            $options = $direction;
            $direction = 1;
        }

        $type = $options['type'] ?? 'basic';

        $this->orders[] = compact('column', 'direction', 'type', 'options');

        return $this;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     */
    public function get($columns = ['*']): ElasticCollection|array
    {
        if ($this->asDsl) {
            return $this->toDsl();
        }
        $original = $this->columns;

        if (is_null($original)) {
            $this->columns = $columns;
        }

        $results = $this->getResultsOnce();
        $this->columns = $original;
        $collection = ElasticCollection::make($results);
        $collection->setQueryMeta($this->metaTransfer);

        return $collection;
    }

    public function getRaw(): mixed
    {
        return $this->runSelect()->asArray();
    }

    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return Elasticsearch
     */
    protected function runSelect()
    {
        return $this->connection->select($this->toCompiledQuery());
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
     * Get a generator for the given query.
     *
     * @return Generator
     */
    public function cursor($scrollTimeout = '30s')
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
    public function exists(): bool
    {
        $this->applyBeforeQueryCallbacks();

        $select = collect($this->getRaw())->dot();

        return $select->has('hits.total.value') && $select->get('hits.total.value') > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function count($columns = null, array $options = []): int|array
    {
        // If columns are specified, we will aggregate the count of the specified columns.
        //        if ($columns) {
        //            return $this->aggregate(__FUNCTION__, Arr::wrap($columns), $options);
        //        }

        // Otherwise, we will just count the records.
        if ($this->asDsl) {
            return $this->grammar->compileCount($this);
        }

        return $this->connection->count($this->grammar->compileCount($this));
    }

    /**
     * {@inheritdoc}
     */
    public function insert(array $values): MetaDTO|bool
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
     * Same as insert, but returns the ES data of the operation.
     */
    public function bulkInsert(array $values): array
    {
        return $this->processor->processBulkInsert($this, $this->connection->insert($this->grammar->compileInsert($this, $values), [], true));
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $values)
    {
        return $this->processor->processUpdate($this, parent::update($values));
    }

    /**
     * {@inheritdoc}
     */
    public function incrementEach(array $columns, array $extra = [])
    {
        return $this->buildCrementEach($columns, 'increment', $extra);
    }

    /**
     * {@inheritdoc}
     */
    public function decrementEach(array $columns, array $extra = [])
    {
        return $this->buildCrementEach($columns, 'decrement', $extra);
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

    public function truncate()
    {
        $this->applyBeforeQueryCallbacks();

        return $this->processor->processDelete($this, $this->connection->delete($this->grammar->compileTruncate($this)));
    }

    /**
     * {@inheritdoc}
     */
    public function newQuery($from = null)
    {
        $query = new static($this->connection, $this->grammar, $this->processor);
        if ($from) {
            $query->from($from);
        }
        // Transfer items
        $query->options()->set($this->options()->all());

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function chunk($count, callable $callback, $scrollTimeout = '30s')
    {
        if (! $this->connection->allowIdSort) {
            return $this->chunkByPit($count, $callback);
        }

        $this->enforceOrderBy();

        foreach ($this->connection->searchResponseIterator($this->toCompiledQuery(), $scrollTimeout, $count) as $results) {
            $page = $results['_scroll_id'];
            $results = collect($this->processor->processSelect($this, $results));

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if ($callback($results, $page) === false) {
                return false;
            }
        }

        return true;
    }

    // ======================================================================
    // ES Specific Methods
    // ======================================================================

    // ----------------------------------------------------------------------
    // Wheres (targeting a field)
    // ----------------------------------------------------------------------

    public function whereTimestamp($column, $operator, $value = null, $boolean = 'and', $options = [])
    {
        [$column, $operator, $value, $boolean, $options] = $this->extractOptionsWithOperator('Where', $column, $operator, $value, $boolean, $options);
        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() == 2
        );
        $value = $this->prepareTimestamp($value);
        $value = (int) $value;
        $type = 'Date';
        $this->wheres[] = compact('column', 'operator', 'value', 'type', 'boolean', 'options');

        return $this;
    }

    public function orWhereTimestamp($column, $operator, $value = null, $options = [])
    {
        return $this->whereTimestamp($column, $operator, $value, 'or', $options);
    }

    /**
     * Add a "where weekday" statement to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  DateTimeInterface|string  $value
     * @param  string  $boolean
     * @return BaseBuilder|static
     */
    public function whereWeekday($column, $operator, $value = null, $boolean = 'and', $options = [])
    {
        [$column, $operator, $value, $boolean, $options] = $this->extractOptionsWithOperator('Date', $column, $operator, $value, $boolean, $options);
        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('N');
        }

        return $this->addDateBasedWhere('Weekday', $column, $operator, $value, $boolean, $options);
    }

    /**
     * Add an "or where weekday" statement to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  DateTimeInterface|string  $value
     * @return BaseBuilder|static
     */
    public function orWhereWeekday($column, $operator, $value = null, $options = [])
    {
        return $this->whereWeekday($column, $operator, $value, 'or', $options);
    }

    // Match: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-query.html

    public function whereMatch($column, $value, $boolean = 'and', $not = false, $options = []): self
    {
        $type = 'Match';
        [$column, $value, $not, $boolean, $options] = $this->extractOptionsWithNot($type, $column, $value, $boolean, $not, $options);
        $this->wheres[] = compact('column', 'value', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    public function orWhereMatch(string $column, $value, $options = []): self
    {
        return $this->whereMatch($column, $value, 'or', false, $options);
    }

    public function whereNotMatch(string $column, $value, $options = []): self
    {
        return $this->whereMatch($column, $value, 'and', true, $options);
    }

    public function orWhereNotMatch(string $column, $value, $options = []): self
    {
        return $this->whereMatch($column, $value, 'or', true, $options);
    }

    // Match Phrase: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-query-phrase.html

    public function wherePhrase($column, $value, $boolean = 'and', $not = false, $options = [])
    {
        $type = 'Phrase';
        [$column, $value, $not, $boolean, $options] = $this->extractOptionsWithNot($type, $column, $value, $boolean, $not, $options);

        $this->wheres[] = compact('column', 'value', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    public function orWherePhrase($column, $value, $options = [])
    {
        return $this->wherePhrase($column, $value, 'or', false, $options);
    }

    public function whereNotPhrase($column, $value, $options = [])
    {
        return $this->wherePhrase($column, $value, 'and', true, $options);
    }

    public function orWhereNotPhrase($column, $value, $options = [])
    {
        return $this->wherePhrase($column, $value, 'or', true, $options);
    }

    // Match Phrase Prefix: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-match-query-phrase-prefix.html

    public function wherePhrasePrefix($column, $value, $boolean = 'and', $not = false, $options = [])
    {
        $type = 'PhrasePrefix';
        [$column, $value, $not, $boolean, $options] = $this->extractOptionsWithNot($type, $column, $value, $boolean, $not, $options);

        $this->wheres[] = compact('column', 'value', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    public function orWherePhrasePrefix($column, $value, $options = [])
    {
        return $this->wherePhrasePrefix($column, $value, 'or', false, $options);
    }

    public function whereNotPhrasePrefix($column, $value, $options = [])
    {
        return $this->wherePhrasePrefix($column, $value, 'and', true, $options);
    }

    public function orWhereNotPhrasePrefix($column, $value, $options = [])
    {
        return $this->wherePhrasePrefix($column, $value, 'or', true, $options);
    }

    /**
     * Add a term query: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-term-query.html
     *
     * @param  string  $boolean
     */
    public function whereTerm($column, $value, $boolean = 'and', $not = false, $options = []): self
    {
        $type = 'Term';
        [$column, $value, $not, $boolean, $options] = $this->extractOptionsWithNot($type, $column, $value, $boolean, $not, $options);
        $this->wheres[] = compact('column', 'value', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    public function orWhereTerm(string $column, $value, $options = []): self
    {
        return $this->whereTerm($column, $value, 'or', false, $options);
    }

    public function whereNotTerm(string $column, $value, $options = []): self
    {
        return $this->whereTerm($column, $value, 'and', true, $options);
    }

    public function orWhereNotTerm(string $column, $value, $options = []): self
    {
        return $this->whereTerm($column, $value, 'or', true, $options);
    }

    // Alias for whereTerm

    public function whereExact($column, $value, $boolean = 'and', $not = false, $options = []): self
    {
        return $this->whereTerm($column, $value, $boolean, $not, $options);
    }

    public function orWhereExact($column, $value, $options = []): self
    {
        return $this->whereTerm($column, $value, 'or', false, $options);
    }

    public function whereNotExact($column, $value, $options = []): self
    {
        return $this->whereTerm($column, $value, 'and', true, $options);
    }

    public function orWhereNotExact($column, $value, $options = []): self
    {
        return $this->whereTerm($column, $value, 'or', true, $options);
    }

    /**
     * Returns documents that contain an indexed value for a field.
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-exists-query.html
     *
     * @param  string  $column
     * @param  string  $boolean
     * @param  bool  $not
     */
    public function whereTermExists($column, $boolean = 'and', $not = false): self
    {
        $this->wheres[] = [
            'type' => 'Basic',
            'operator' => 'exists',
            'column' => $column,
            'value' => $not ? null : ' ',
            'boolean' => $boolean,
            'options' => [],
        ];

        return $this;
    }

    public function whereNotTermExists($column)
    {
        return $this->whereTermExists($column, 'and', true);
    }

    public function orWhereTermExists($column)
    {
        return $this->whereTermExists($column, 'or', false);
    }

    public function orWhereNotTermsExists($column)
    {
        return $this->whereTermExists($column, 'or', true);
    }

    /**
     * Add a fuzzy term query
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-fuzzy-query.html
     *
     * @param  string  $boolean
     */
    public function whereFuzzy($column, $value, $boolean = 'and', $not = false, $options = []): self
    {
        $type = 'Fuzzy';
        [$column, $value, $not, $boolean, $options] = $this->extractOptionsWithNot($type, $column, $value, $boolean, $not, $options);
        $this->wheres[] = compact('column', 'value', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    public function orWhereFuzzy(string $column, $value, array $options = []): self
    {
        return $this->whereFuzzy($column, $value, 'or', false, $options);
    }

    public function whereNotFuzzy(string $column, $value, array $options = []): self
    {
        return $this->whereFuzzy($column, $value, 'and', true, $options);
    }

    public function orWhereNotFuzzy(string $column, $value, array $options = []): self
    {
        return $this->whereFuzzy($column, $value, 'or', true, $options);
    }

    /**
     * Add a prefix query
     *
     * @param  string  $column
     * @param  string  $boolean
     * @param  bool  $not
     * @param  array  $options
     */
    public function whereStartsWith($column, string $value, $boolean = 'and', $not = false, $options = []): self
    {
        $type = 'Prefix';
        [$column, $value, $not, $boolean, $options] = $this->extractOptionsWithNot($type, $column, $value, $boolean, $not, $options);
        $this->wheres[] = compact('column', 'value', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    public function orWhereStartsWith($column, string $value, $options = []): self
    {
        return $this->whereStartsWith($column, $value, 'or', false, $options);
    }

    public function whereNotStartsWith($column, string $value, $options = [])
    {
        return $this->whereStartsWith($column, $value, 'and', true, $options);
    }

    public function orWhereNotStartsWith($column, string $value, $options = [])
    {
        return $this->whereStartsWith($column, $value, 'or', true, $options);
    }

    // Alias for whereStartsWith

    public function wherePrefix($column, string $value, $boolean = 'and', $not = false, $options = []): self
    {
        return $this->whereStartsWith($column, $value, $boolean, $not, $options);
    }

    public function orWherePrefix($column, string $value, $options = []): self
    {
        return $this->whereStartsWith($column, $value, 'or', false, $options);
    }

    public function whereNotPrefix($column, string $value, $options = []): self
    {
        return $this->whereStartsWith($column, $value, 'and', true, $options);
    }

    public function orWhereNotPrefix($column, string $value, $options = []): self
    {
        return $this->whereStartsWith($column, $value, 'or', true, $options);
    }

    /**
     * Add a 'distance from point' statement to the query.
     *
     * @param  string  $field
     */
    public function whereGeoBox($field, array $topLeft, array $bottomRight, $validationMethod = null, $boolean = 'and', bool $not = false): self
    {
        $column = $field;
        $bounds = [
            'top_left' => $topLeft,
            'bottom_right' => $bottomRight,
        ];
        $options = [];
        if ($validationMethod) {
            $options['validation_method'] = $validationMethod;
        }
        $type = 'GeoBox';
        $this->wheres[] = [
            'column' => $column,
            'bounds' => $bounds,
            'type' => $type,
            'boolean' => $boolean,
            'not' => $not,
            'options' => $options,
        ];

        return $this;
    }

    public function orWhereGeoBox($field, array $topLeft, array $bottomRight, $validationMethod = null): self
    {
        return $this->whereGeoBox($field, $topLeft, $bottomRight, $validationMethod, 'or');
    }

    public function whereNotGeoBox($field, array $topLeft, array $bottomRight, $validationMethod = null): self
    {
        return $this->whereGeoBox($field, $topLeft, $bottomRight, $validationMethod, 'and', true);
    }

    public function orWhereNotGeoBox($field, array $topLeft, array $bottomRight, $validationMethod = null): self
    {
        return $this->whereGeoBox($field, $topLeft, $bottomRight, $validationMethod, 'or', true);
    }

    /**
     * Add a 'distance from point' statement to the query.
     *
     * @param  string  $field
     * @param  string  $boolean
     */
    public function whereGeoDistance($field, string $distance, array $location, $distanceType = null, $validationMethod = null, $boolean = 'and', bool $not = false): self
    {
        $column = $field;
        $type = 'GeoDistance';
        $options = [];
        if ($distanceType) {
            $options['distance_type'] = $distanceType;
        }
        if ($validationMethod) {
            $options['validation_method'] = $validationMethod;
        }

        $this->wheres[] = compact('column', 'location', 'distance', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    public function orWhereGeoDistance($field, string $distance, array $location, $distanceType = null, $validationMethod = null): self
    {
        return $this->whereGeoDistance($field, $distance, $location, $distanceType, $validationMethod, 'or', false);
    }

    public function whereNotGeoDistance($field, string $distance, array $location, $distanceType = null, $validationMethod = null): self
    {
        return $this->whereGeoDistance($field, $distance, $location, $distanceType, $validationMethod, 'and', true);
    }

    public function orWhereNotGeoDistance($field, string $distance, array $location, $distanceType = null, $validationMethod = null): self
    {
        return $this->whereGeoDistance($field, $distance, $location, $distanceType, $validationMethod, 'or', true);
    }

    /**
     * Add a 'nested' statement to the query.
     *
     * @param  string  $column
     * @param  callable|BaseBuilder|static  $query
     * @param  bool  $filterInnerHits
     * @param  array  $options
     * @param  string  $boolean
     * @param  bool  $not
     */
    public function whereNestedObject($column, $query, $filterInnerHits = false, $options = [], $boolean = 'and', $not = false): self
    {

        $from = $this->from;
        $type = 'NestedObject';
        $options = $this->setOptions($options, 'nested');
        if ($filterInnerHits) {
            $options->innerHits(true);
        }
        $options = $options->toArray();

        $this->options()->add('parentField', $column);
        if (! is_string($query) && is_callable($query)) {
            call_user_func($query, $query = $this->newQuery($from));
        }

        $this->wheres[] = compact('column', 'query', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    public function orWhereNestedObject($column, $query, $filterInnerHits = false, $options = []): self
    {
        return $this->whereNestedObject($column, $query, $filterInnerHits, $options, 'or');
    }

    public function whereNotNestedObject($column, $query, $filterInnerHits = false, $options = []): self
    {
        return $this->whereNestedObject($column, $query, $filterInnerHits, $options, 'and', true);
    }

    public function orWhereNotNestedObject($column, $query, $filterInnerHits = false, $options = []): self
    {
        return $this->whereNestedObject($column, $query, $filterInnerHits, $options, 'or', true);
    }

    public function filterNested($column, $query, $options = [])
    {
        $currentWheres = collect($this->wheres);
        $existing = $currentWheres->where('type', 'InnerNested')->where('column', $column)->first();
        if ($existing) {
            throw new RuntimeException("Nested filter for field '{$column}' already exists.");
        }
        $boolean = 'and';
        $not = false;
        $from = $this->from;
        $type = 'InnerNested';
        $options = $this->setOptions($options, 'nested');
        $options = $options->toArray();

        $this->options()->add('parentField', $column);
        if (! is_string($query) && is_callable($query)) {
            call_user_func($query, $query = $this->newQuery($from));
        }

        $this->wheres[] = compact('column', 'query', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    /**
     * Add a 'regexp' statement to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     */
    public function whereRegex($column, string $value, $boolean = 'and', bool $not = false, array $options = []): self
    {
        $type = 'Regex';
        [$column, $value, $not, $boolean, $options] = $this->extractOptionsWithNot($type, $column, $value, $boolean, $not, $options);
        $this->wheres[] = compact('column', 'value', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    public function orWhereRegex($column, string $value, array $options = []): self
    {
        return $this->whereRegex($column, $value, 'or', false, $options);
    }

    public function whereNotRegex($column, string $value, array $options = []): self
    {
        return $this->whereRegex($column, $value, 'and', true, $options);
    }

    public function orWhereNotRegex($column, string $value, array $options = []): self
    {
        return $this->whereRegex($column, $value, 'or', true, $options);
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

    // ----------------------------------------------------------------------
    // Ordering
    // ----------------------------------------------------------------------

    public function orderByGeo(string $column, array $coordinates, $direction = 1, array $options = []): self
    {

        if (is_array($direction)) {
            $options = $direction;
            $direction = 1;
        }
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

    public function orderByNested(string $column, $direction = 1, string $mode = 'avg'): self
    {
        $options = ['mode' => $mode];
        $options = [
            ...$options,
            'nested' => ['path' => Str::beforeLast($column, '.')],
        ];

        return $this->orderBy($column, $direction, $options);
    }

    public function orderByNestedDesc(string $column, string $mode = 'avg'): self
    {
        return $this->orderByNested($column, 'desc', $mode);
    }

    public function withSort(string $column, $key, $value): self
    {
        $this->sorts[$column] = [$key => $value];

        return $this;
    }

    // ----------------------------------------------------------------------
    // Options
    // ----------------------------------------------------------------------

    /**
     * Add highlights to query.
     *
     * @param  string|string[]  $columns
     */
    public function highlight($columns = ['*'], $preTag = '<em>', $postTag = '</em>', array $options = []): self
    {
        $column = Arr::wrap($columns);

        $this->highlight = compact('column', 'preTag', 'postTag', 'options');

        return $this;
    }

    /**
     * @return mixed|null
     */
    public function getOption(string $option, mixed $default = null)
    {
        return $this->options()->get($option, $default);
    }

    public function proceedOnConflicts(): self
    {
        return $this->onConflicts(self::CONFLICT['PROCEED']);
    }

    /**
     * Set how to handle conflicts during a delete request
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-delete-by-query.html#docs-delete-by-query-api-query-params
     *
     * @throws \Exception
     */
    public function onConflicts(string $option = self::CONFLICT['PROCEED']): self
    {
        if (in_array($option, self::CONFLICT)) {
            $this->options()->add('conflicts', 'proceed');

            return $this;
        }

        throw new \Exception(
            "$option is an invalid conflict option, valid options are: ".implode(', ', self::CONFLICT)
        );
    }

    /**
     * Whether to include inner hits in the response
     */
    public function withInnerHits(): self
    {
        $this->includeInnerHits = true;

        return $this;
    }

    public function withoutRefresh(): self
    {
        // Add the `refresh` option to the model or query
        $this->options()->add('refresh', false);

        return $this;
    }

    // ----------------------------------------------------------------------
    // Ops
    // ----------------------------------------------------------------------

    /**
     * Add a filter query by calling the required 'where' method
     * and capturing the added whereas a filter
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
     * Adds a function score of any type
     *
     * @param  string  $functionType
     * @param  array  $functionOptions  see elastic search docs for options
     */
    public function functionScore($functionType, array $functionOptions, callable $query, string $boolean = 'and'): self
    {
        $type = 'FunctionScore';
        $options = $functionOptions;

        call_user_func($query, $query = $this->newQuery($this->from));

        $this->wheres[] = compact('functionType', 'query', 'type', 'boolean', 'options');

        return $this;
    }

    /**
     * returns the fully qualified index
     */
    public function getFrom(): string
    {
        return Sanitizer::qualifiedIndex($this->connection->getTablePrefix(), $this->from, $this->getIndexSuffix());
    }

    /**
     * Set the suffix that is appended to from.
     */
    public function getIndexSuffix(): string
    {
        return $this->options()->get('suffix', '');
    }

    public function withSuffix(string $suffix): self
    {
        $this->options()->add('suffix', $suffix);

        return $this;
    }

    public function getLimit(): int
    {
        return $this->getSetLimit() ?? $this->getDefaultLimit() ?? $this->connection->getDefaultLimit();
    }

    public function getSetLimit(): ?int
    {
        return $this->options()->get('limit', $this->limit) ?? null;
    }

    public function getDefaultLimit(): ?int
    {
        return $this->options()->get('default_limit', $this->limit) ?? null;
    }

    protected function hasProcessedSelect(): bool
    {
        if ($this->results === null) {
            return false;
        }

        return $this->offset === $this->resultsOffset;
    }

    /**
     * Get the Elasticsearch representation of the query.
     */
    public function toCompiledQuery(): array|string
    {
        return $this->toSql();
    }

    /**
     * Get mappings without re-fetching for subsequent calls.
     *
     * @return array
     */
    public function getMapping()
    {
        if (empty($this->mapping)) {
            $index = $this->getFrom();
            $this->mapping = Schema::connection($this->connection->getName())->getFieldsMapping($index);
        }

        return $this->mapping;
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

    // ----------------------------------------------------------------------
    // Relations & Routing
    // ----------------------------------------------------------------------

    public function routing(string $routing): self
    {
        $this->routing = $routing;

        return $this;
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
     * Set the parent ID to be used when routing queries to Elasticsearch
     */
    public function parentId(string $id): self
    {
        $this->parentId = $id;

        return $this;
    }

    /**
     * Add a where child statement to the query.
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
     * Add a where relationship statement to the query.
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
     * Add a where parent statement to the query.
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
     * @param  string  $parentType  Name of the parent relation from the join mapping
     * @param  mixed  $id
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

    public function raw($value): Elasticsearch
    {
        return $this->connection->raw($value);
    }

    public function processedRaw($dsl): ?array
    {
        return $this->processor->processRaw($this, $this->connection->raw($dsl));
    }

    // ----------------------------------------------------------------------
    // Body Parameter Methods
    // ----------------------------------------------------------------------

    public function excludeFields(string|array $fields): self
    {
        $fields = Arr::wrap($fields);
        $fieldCsv = implode(',', $fields);
        $this->bodyParameters['_source_excludes'] = $fieldCsv;

        return $this;
    }

    public function withMinScore(float $val): self
    {
        $this->bodyParameters['min_score'] = $val;

        return $this;
    }

    public function withAnalyzer(string $analyzer): self
    {
        $this->bodyParameters['analyzer'] = $analyzer;

        return $this;
    }

    public function withTrackTotalHits(bool|int|null $val = true): self
    {
        if ($val === null) {
            return $this->withoutTrackTotalHits();
        }
        $this->bodyParameters['track_total_hits'] = $val;

        return $this;
    }

    public function withoutTrackTotalHits(): self
    {
        unset($this->bodyParameters['track_total_hits']);

        return $this;
    }

    // ----------------------------------------------------------------------
    // Internal Operations
    // ----------------------------------------------------------------------

    // @internal
    public function setMetaTransfer(MetaDTO $metaTransfer): void
    {
        $this->metaTransfer = $metaTransfer;
    }

    // @internal
    public function getMetaTransfer(): ?MetaDTO
    {
        if (! $this->metaTransfer) {
            $this->metaTransfer = new MetaDTO([]);
        }

        return $this->metaTransfer;
    }

    // @internal
    public function inferIndex(): string
    {
        $prefix = $this->connection->getTablePrefix();
        $table = $this->from;
        $suffix = $this->options()->get('suffix', '');

        return $prefix.$table.$suffix;
    }

    private function prepareTimestamp($value): string|int
    {
        if (is_numeric($value)) {
            // Convert to integer in case it's a string
            $value = (int) $value;
            // Check for milliseconds
            if ($value > 10000000000) {
                return $value;
            }

            // ES expects seconds as a string
            return (string) Carbon::createFromTimestamp($value)->timestamp;
        }

        // If it's not numeric, assume it's a date string and try to return TS as a string
        try {
            return (string) Carbon::parse($value)->timestamp;
        } catch (Exception $e) {
            throw new LogicException('Invalid date or timestamp');
        }
    }

    // ----------------------------------------------------------------------
    // V4 Backwards Compatibility
    // ----------------------------------------------------------------------

    /**
     * @deprecated v5.0.0
     * @see whereGeoDistance()
     */
    public function filterGeoPoint($field, $distance, $geoPoint)
    {
        return $this->whereGeoDistance($field, $distance, $geoPoint);
    }

    /**
     * @deprecated v5.0.0
     * @see whereGeoBox()
     */
    public function filterGeoBox($field, $topLeft, $bottomRight)
    {
        return $this->whereGeoBox($field, $topLeft, $bottomRight);
    }

    /**
     * @deprecated v5.0.0
     * @see filterNested()
     */
    public function queryNested($column, $callBack)
    {
        return $this->filterNested($column, $callBack);
    }
}

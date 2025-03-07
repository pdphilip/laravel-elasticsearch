<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query;

use Closure;
use DateTimeInterface;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Data\MetaDTO;
use PDPhilip\Elasticsearch\Eloquent\ElasticCollection;
use PDPhilip\Elasticsearch\Exceptions\BuilderException;
use PDPhilip\Elasticsearch\Helpers\Sanitizer;
use PDPhilip\Elasticsearch\Schema\Schema;
use PDPhilip\Elasticsearch\Traits\HasOptions;
use PDPhilip\Elasticsearch\Traits\Query\ManagesOptions;

/**
 * @property Connection $connection
 * @property Processor $processor
 * @property Grammar $grammar
 */
class Builder extends BaseBuilder
{
    use HasOptions;
    use ManagesOptions;

    /** @var string[] */
    public const CONFLICT = [
        'ABORT' => 'abort',
        'PROCEED' => 'proceed',
    ];

    public array $bucketAggregations = [];

    public $distinct;

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

    public mixed $afterKey = null;

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

    protected $savedIds = [];

    protected $parentId;

    protected $results;

    /** @var int */
    protected $resultsOffset;

    protected $routing;

    protected ?MetaDTO $metaTransfer = null;

    public function __call($method, $parameters)
    {
        if (Str::startsWith($method, 'filterWhere')) {
            return $this->dynamicFilter($method, $parameters);
        }

        return parent::__call($method, $parameters);
    }

    public function toDsl(): array
    {
        $this->applyBeforeQueryCallbacks();

        return $this->grammar->compileSelect($this);
    }

    public function toSql(): array|string
    {
        return $this->toDsl();
    }

    // ======================================================================
    // Inherited Methods
    // ======================================================================

    /**
     * Force the query to only return distinct results.
     *
     * @return $this
     */
    public function distinct(bool $includeCount = false)
    {
        $this->distinctCount = $includeCount;
        $this->distinct = true;

        return $this;
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
     * {@inheritdoc}
     */
    public function groupBy(...$groups)
    {
        $groups = Sanitizer::cleanArrayValues($groups);

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
        if (in_array($column, ['_score', '_count'])) {
            $this->sorts[$column] = $direction;

            return $this;
        }

        $type = isset($options['type']) ? $options['type'] : 'basic';

        $this->orders[] = compact('column', 'direction', 'type', 'options');

        return $this;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     */
    public function get($columns = ['*']): ElasticCollection
    {

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
     * @return \Generator
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
    public function count($columns = '*', array $options = [])
    {
        return $this->aggregate(__FUNCTION__, Arr::wrap($columns), $options);
    }

    /**
     * {@inheritdoc}
     *
     * @param  Expression|string|array  $column
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
     * {@inheritdoc}
     */
    public function avg($column, array $options = [])
    {
        return $this->aggregate(__FUNCTION__, Arr::wrap($column), $options);
    }

    public function aggregate($function, $columns = ['*'], $options = [])
    {
        return $this->aggregateMetric($function, $columns, $options);
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

    public function orWhereTermExists($column, $options = [])
    {
        return $this->whereTermExists($column, 'or', false);
    }

    public function orWhereNotTermsExists($column, $options = [])
    {
        return $this->whereTermExists($column, 'or', true);
    }

    /**
     * Add a fuzzy term query
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-fuzzy-query.html
     *
     * @param  string  $boolean
     */
    public function whereTermFuzzy($column, $value, $boolean = 'and', $not = false, $options = []): self
    {
        $type = 'TermFuzzy';
        [$column, $value, $not, $boolean, $options] = $this->extractOptionsWithNot($type, $column, $value, $boolean, $not, $options);
        $this->wheres[] = compact('column', 'value', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    public function orWhereTermFuzzy(string $column, $value, array $options = []): self
    {
        return $this->whereTermFuzzy($column, $value, 'or', false, $options);
    }

    public function whereNotTermFuzzy(string $column, $value, array $options = []): self
    {
        return $this->whereTermFuzzy($column, $value, 'and', true, $options);
    }

    public function orWhereNotTermFuzzy(string $column, $value, array $options = []): self
    {
        return $this->whereTermFuzzy($column, $value, 'or', true, $options);
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
     * @param  string  $column
     */
    public function whereGeoBoundsIn($column, array $bounds, $validationMethod = null, $boolean = 'and', bool $not = false): self
    {
        $options = [];
        if ($validationMethod) {
            $options['validation_method'] = $validationMethod;
        }
        $type = 'GeoBoundsIn';
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

    public function orWhereGeoBoundsIn($column, array $bounds, $validationMethod = null): self
    {
        return $this->whereGeoBoundsIn($column, $bounds, $validationMethod, 'or');
    }

    public function whereNotGeoBoundsIn($column, array $bounds, $validationMethod = null): self
    {
        return $this->whereGeoBoundsIn($column, $bounds, $validationMethod, 'and', true);
    }

    public function orWhereNotGeoBoundsIn($column, array $bounds, $validationMethod = null): self
    {
        return $this->whereGeoBoundsIn($column, $bounds, $validationMethod, 'or', true);
    }

    /**
     * Add a 'distance from point' statement to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     */
    public function whereGeoDistance($column, array $location, string $distance, $distanceType = null, $validationMethod = null, $boolean = 'and', bool $not = false): self
    {
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

    public function orWhereGeoDistance($column, array $location, string $distance, $distanceType = null, $validationMethod = null): self
    {
        return $this->whereGeoDistance($column, $location, $distance, $distanceType, $validationMethod, 'or', false);
    }

    public function whereNotGeoDistance($column, array $location, string $distance, $distanceType = null, $validationMethod = null): self
    {
        return $this->whereGeoDistance($column, $location, $distance, $distanceType, $validationMethod, 'and', true);
    }

    public function orWhereNotGeoDistance($column, array $location, string $distance, $distanceType = null, $validationMethod = null): self
    {
        return $this->whereGeoDistance($column, $location, $distance, $distanceType, $validationMethod, 'or', true);
    }

    /**
     * Add a 'nested' statement to the query.
     *
     * @param  string  $column
     * @param  callable|BaseBuilder|static  $query
     * @param  string  $boolean
     */
    public function whereNestedObject($column, $query, $innerHits = true, $options = [], $boolean = 'and', $not = false): self
    {
        $from = $this->from;
        $type = 'NestedObject';
        $options = $this->setOptions($options, 'nested');
        $options->innerHits($innerHits);
        $options = $options->toArray();

        if (! is_string($query) && is_callable($query)) {
            call_user_func($query, $query = $this->newQuery($from));
        }
        $this->wheres[] = compact('column', 'query', 'type', 'boolean', 'not', 'options');

        return $this;
    }

    public function orWhereNestedObject($column, $query, $innerHits = true, $options = []): self
    {
        return $this->whereNestedObject($column, $query, $innerHits, $options, 'or');
    }

    public function whereNotNestedObject($column, $query, $innerHits = true, $options = []): self
    {
        return $this->whereNestedObject($column, $query, $innerHits, $options, 'and', true);
    }

    public function orWhereNotNestedObject($column, $query, $innerHits = true, $options = []): self
    {
        return $this->whereNestedObject($column, $query, $innerHits, $options, 'or', true);
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
    // Search (Multiple Fields)
    // ----------------------------------------------------------------------

    /**
     * Add a text search clause to the query.
     *
     * @param  array|Closure  $options
     */
    public function search(string $query, string $type = 'best_fields', ?array $columns = null, mixed $options = [], bool $not = false, string $boolean = 'and'): self
    {
        $options = $this->setOptions($options, 'search');
        $options->asType($type);
        if ($columns) {
            $options->fields(Arr::wrap($columns));
            $options->formatFields();
        }
        $this->wheres[] = [
            'type' => 'Search',
            'value' => $query,
            'boolean' => $boolean,
            'not' => $not,
            'options' => $options->toArray(),
        ];

        return $this;
    }

    // Multi Match query with type:best_fields
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#type-best-fields

    public function searchTerm($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'best_fields', $columns, $options);
    }

    public function orSearchTerm($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'best_fields', $columns, $options, false, 'or');
    }

    public function searchNotTerm($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'best_fields', $columns, $options, true);
    }

    public function orSearchNotTerm($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'best_fields', $columns, $options, true, 'or');
    }

    // Multi Match query with type:most_fields
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#type-most-fields

    public function searchTermMost($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'most_fields', $columns, $options);
    }

    public function orSearchTermMost($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'most_fields', $columns, $options, false, 'or');
    }

    public function searchNotTermMost($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'most_fields', $columns, $options, true);
    }

    public function orSearchNotTermMost($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'most_fields', $columns, $options, true, 'or');
    }

    // Multi Match query with type:cross_fields
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#type-cross-fields

    public function searchTermCross($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'cross_fields', $columns, $options);
    }

    public function orSearchTermCross($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'cross_fields', $columns, $options, false, 'or');
    }

    public function searchNotTermCross($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'cross_fields', $columns, $options, true);
    }

    public function orSearchNotTermCross($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'cross_fields', $columns, $options, true, 'or');
    }

    // Multi Match query with type:phrase
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#type-phrase

    public function searchPhrase($phrase, ?array $columns = null, $options = [])
    {
        return $this->search($phrase, 'phrase', $columns, $options);
    }

    public function orSearchPhrase($phrase, ?array $columns = null, $options = [])
    {
        return $this->search($phrase, 'phrase', $columns, $options, false, 'or');
    }

    public function searchNotPhrase($phrase, ?array $columns = null, $options = [])
    {
        return $this->search($phrase, 'phrase', $columns, $options, true);
    }

    public function orSearchNotPhrase($phrase, ?array $columns = null, $options = [])
    {
        return $this->search($phrase, 'phrase', $columns, $options, true, 'or');
    }

    // Multi Match query with type:phrase_prefix
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#type-phrase

    public function searchPhrasePrefix($phrase, ?array $columns = null, $options = [])
    {
        return $this->search($phrase, 'phrase_prefix', $columns, $options);
    }

    public function orSearchPhrasePrefix($terms, ?array $columns = null, $options = [])
    {
        return $this->search($terms, 'phrase_prefix', $columns, $options, false, 'or');
    }

    public function searchNotPhrasePrefix($phrase, ?array $columns = null, $options = [])
    {
        return $this->search($phrase, 'phrase_prefix', $columns, $options, true);
    }

    public function orSearchNotPhrasePrefix($phrase, ?array $columns = null, $options = [])
    {
        return $this->search($phrase, 'phrase_prefix', $columns, $options, true, 'or');
    }

    // Multi Match query with type:bool_prefix
    // https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html#type-bool-prefix

    public function searchBoolPrefix($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'bool_prefix', $columns, $options);
    }

    public function orSearchBoolPrefix($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'bool_prefix', $columns, $options, false, 'or');
    }

    public function searchNotBoolPrefix($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'bool_prefix', $columns, $options, true);
    }

    public function orSearchNotBoolPrefix($query, ?array $columns = null, $options = [])
    {
        return $this->search($query, 'bool_prefix', $columns, $options, true, 'or');
    }

    // ----------------------------------------------------------------------
    // Ordering
    // ----------------------------------------------------------------------

    public function orderByGeo(string $column, array $coordinates, $direction = 1, array $options = []): self
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

    public function orderByNested(string $column, $direction = 1, array $options = []): self
    {

        $options = [
            ...$options,
            'nested' => ['path' => Str::beforeLast($column, '.')],
        ];

        return $this->orderBy($column, $direction, $options);
    }

    // ----------------------------------------------------------------------
    // Aggregations & Stats
    // ----------------------------------------------------------------------

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
     * @return array|mixed
     *
     * @throws BuilderException
     */
    protected function aggregateMetric($function, $columns = ['*'], $options = [])
    {
        // Each column we want aggregated
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

    /**
     * Adds a bucket aggregation to the current query.
     *
     * @param  string  $key  The key for the bucket.
     * @param  string|null  $type  The type of aggregation.
     * @param  mixed|null  $args  The arguments for the aggregation.
     *                            Can be a callable to generate the arguments using a new query.
     * @param  mixed|null  $aggregations  The sub-aggregations or nested aggregations.
     *                                    Can be a callable to generate them using a new query.
     * @return self The current query builder instance.
     */
    public function bucket($key, $type = null, $args = null, $aggregations = null): self
    {
        return $this->bucketAggregation($key, $type, $args, $aggregations);
    }

    /**
     * Get the aggregations returned from query
     */
    public function getAggregationResults(): array|Collection
    {
        $this->getResultsOnce();

        return $this->processor->getAggregationResults();
    }

    /**
     * Get the raw aggregations returned from query
     */
    public function getRawAggregationResults(): array
    {
        $this->getResultsOnce();

        return $this->processor->getRawAggregationResults();
    }

    /**
     * Adds a bucket aggregation to the current query.
     *
     * @param  string  $key  The key for the bucket.
     * @param  string|null  $type  The type of aggregation.
     * @param  mixed|null  $args  The arguments for the aggregation.
     *                            Can be a callable to generate the arguments using a new query.
     * @param  mixed|null  $aggregations  The sub-aggregations or nested aggregations.
     *                                    Can be a callable to generate them using a new query.
     * @return self The current query builder instance.
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

    // ----------------------------------------------------------------------
    // Scripting
    // ----------------------------------------------------------------------

    /**
     * Add a script query
     *
     * @param  string  $boolean
     */
    public function whereScript(string $script, $boolean = 'and', array $options = []): self
    {
        $type = 'Script';

        $this->wheres[] = compact('script', 'boolean', 'type', 'options');

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

    // ----------------------------------------------------------------------
    // Options
    // ----------------------------------------------------------------------

    /**
     * Add highlights to query.
     *
     * @param  string|string[]  $column
     */
    public function highlight($column = ['*'], $preTag = '<em>', $postTag = '</em>', array $options = []): self
    {
        $column = Arr::wrap($column);

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
     * @param  string  $boolean
     * @param  array  $options  see elastic search docs for options
     */
    public function functionScore($functionType, callable $query, $boolean = 'and', $options = []): self
    {

        $type = 'FunctionScore';

        call_user_func($query, $query = $this->newQuery());

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

    //    public function setIndexSuffix($suffix = null): self
    //    {
    //        if ($suffix) {
    //            $this->options()->add('suffix', $suffix);
    //        }
    //
    //        return $this;
    //    }

    public function getLimit(): int
    {
        return $this->getSetLimit() > 0 ? $this->getSetLimit() : $this->connection->defaultQueryLimit;
    }

    public function getSetLimit(): int
    {
        return $this->options()->get('limit', $this->limit) ?? 0;
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
            $this->mapping = Schema::connection($this->connection->getName())->getFieldsMapping($index, true);
        }

        return $this->mapping;
    }

    public function getGroupByAfterKey($offset): mixed
    {
        $clone = $this->clone();
        $clone->limit = $offset;
        $clone->offset = 0;
        $res = collect($clone->getRaw());

        return $res->pull('aggregations.group_by.after_key');

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
}

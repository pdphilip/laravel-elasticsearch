<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent\Docs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use PDPhilip\Elasticsearch\Data\MetaDTO;
use PDPhilip\Elasticsearch\Eloquent\Builder;
use PDPhilip\Elasticsearch\Eloquent\ElasticCollection;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Pagination\SearchAfterPaginator;

/**
 * Query Builder Methods ---------------------------------
 *
 * @method static $this query()
 * @method static Builder dslQuery()
 *-----------------------------------
 * @method static $this where($column, $operator = null, $value = null, $boolean = 'and', $options = [])
 * @method static $this whereNot($column, $operator = null, $value = null, $boolean = 'and', $options = [])
 * @method static $this orWhere($column, $operator = null, $value = null, $options = [])
 * @method static $this orWhereNot($column, $operator = null, $value = null, $options = [])
 *-----------------------------------
 * @method static $this whereIn($column, $values, $boolean = 'and', $not = false, $options = [])
 * @method static $this whereNotIn($column, $values, $boolean = 'and', $options = [])
 * @method static $this orWhereIn($column, $values, $options = [])
 * @method static $this orWhereNotIn($column, $values, $options = [])
 *-----------------------------------
 * @method static $this whereNull($columns, $boolean = 'and', $not = false)
 * @method static $this whereNotNull($columns, $boolean = 'and')
 * @method static $this orWhereNull($columns)
 * @method static $this orWhereNotNull($columns)
 *-----------------------------------
 * @method static $this whereExact($column, $value, $boolean = 'and', $not = false, $options = [])
 * @method static $this whereNotExact($column, $value, $options = [])
 * @method static $this orWhereExact($column, $value, $options = [])
 * @method static $this orWhereNotExact($column, $value, $options = [])
 *-----------------------------------
 * @method static $this whereFuzzy($column, $value, $boolean = 'and', $not = false, $options = [])
 * @method static $this whereNotFuzzy($column, $value, $options = [])
 * @method static $this orWhereFuzzy($column, $value, $options = [])
 * @method static $this orWhereNotFuzzy($column, $value, $options = [])
 *-----------------------------------
 * @method static $this wherePrefix($column, $value, $boolean = 'and', $not = false, $options = [])
 * @method static $this whereNotPrefix($column, $value, $options = [])
 * @method static $this orWherePrefix($column, $value, $options = [])
 * @method static $this orWhereNotPrefix($column, $value, $options = [])
 *-----------------------------------
 * @method static $this wherePhrase($column, $value, $boolean = 'and', $options = [])
 * @method static $this orWherePhrase($column, $value, $options = [])
 * @method static $this wherePhrasePrefix($column, $value, $boolean = 'and', $options = [])
 * @method static $this orWherePhrasePrefix($column, $value, $options = [])
 *-----------------------------------
 * @method static $this whereDate($column, $operator = null, $value = null, $boolean = 'and', $options = [])
 * @method static $this orWhereDate($column, $operator = null, $value = null, $options = [])
 * @method static $this whereTimestamp($column, $operator = null, $value = null, $boolean = 'and', $options = [])
 * @method static $this orWhereTimestamp($column, $operator = null, $value = null, $options = [])
 * @method static $this whereWeekday($column, $operator, $value = null, $boolean = 'and', $options = [])
 * @method static $this orWhereWeekday($column, $operator, $value = null, $options = [])
 *-----------------------------------
 * @method static $this whereRegex($column, $regex, $options = [])
 * @method static $this orWhereRegex($column, $regex, $options = [])
 *-----------------------------------
 * @method static $this whereBetween($column, $values, $boolean = 'and', $not = false, $options = [])
 * @method static $this whereNotBetween($column, $values, $boolean = 'and', $options = [])
 * @method static $this orWhereBetween($column, $values, $options = [])
 * @method static $this orWhereNotBetween($column, $values, $options = [])
 *-----------------------------------
 * @method static $this whereGeoBox($field, $topLeft, $bottomRight, $validationMethod = null, $boolean = 'and', $not = false)
 * @method static $this whereNotGeoBox($field, $topLeft, $bottomRight, $validationMethod = null)
 * @method static $this orWhereGeoBox($field, $topLeft, $bottomRight, $validationMethod = null)
 * @method static $this orWhereNotGeoBox($field, $topLeft, $bottomRight, $validationMethod = null)
 *-----------------------------------
 * @method static $this whereGeoDistance($field, $distance, $location, $distanceType = null, $validationMethod = null, $boolean = 'and', $not = false)
 * @method static $this whereNotGeoDistance($field, $distance, $location, $distanceType = null, $validationMethod = null)
 * @method static $this orWhereGeoDistance($field, $distance, $location, $distanceType = null, $validationMethod = null)
 * @method static $this orWhereNotGeoDistance($field, $distance, $location, $distanceType = null, $validationMethod = null)
 *-----------------------------------
 * @method static $this whereNestedObject($column, $query, $filterInnerHits = false, $options = [], $boolean = 'and', $not = false)
 * @method static $this whereNotNestedObject($column, $query, $filterInnerHits = false, $options = [])
 * @method static $this orWhereNestedObject($column, $query, $filterInnerHits = false, $options = [])
 * @method static $this orWhereNotNestedObject($column, $query, $filterInnerHits = false, $options = [])
 *-----------------------------------
 * @method static $this whereTermExists($column, $boolean = 'and', $not = false)
 * @method static $this whereNotTermExists($column)
 * @method static $this orWhereTermExists($column)
 * @method static $this orWhereNotTermExists($column)
 *-----------------------------------
 * @method static $this whereRaw($dsl, $bindings = [], $boolean = 'and', $options = [])
 *===========================================
 * Full Text Search Methods
 *===========================================
 * @method static $this searchTerm($query, mixed $columns = null, $options = [])
 * @method static $this orSearchTerm($query, mixed $columns = null, $options = [])
 * @method static $this searchNotTerm($query, mixed $columns = null, $options = [])
 * @method static $this orSearchNotTerm($query, mixed $columns = null, $options = [])
 *-----------------------------------
 * @method static $this searchTermMost($query, mixed $columns = null, $options = [])
 * @method static $this orSearchTermMost($query, mixed $columns = null, $options = [])
 * @method static $this searchNotTermMost($query, mixed $columns = null, $options = [])
 * @method static $this orSearchNotTermMost($query, mixed $columns = null, $options = [])
 *-----------------------------------
 * @method static $this searchTermCross($query, mixed $columns = null, $options = [])
 * @method static $this orSearchTermCross($query, mixed $columns = null, $options = [])
 * @method static $this searchNotTermCross($query, mixed $columns = null, $options = [])
 * @method static $this orSearchNotTermCross($query, mixed $columns = null, $options = [])
 *-----------------------------------
 * @method static $this searchPhrase($phrase, mixed $columns = null, $options = [])
 * @method static $this orSearchPhrase($phrase, mixed $columns = null, $options = [])
 * @method static $this searchNotPhrase($phrase, mixed $columns = null, $options = [])
 * @method static $this orSearchNotPhrase($phrase, mixed $columns = null, $options = [])
 *-----------------------------------
 * @method static $this searchPhrasePrefix($phrase, mixed $columns = null, $options = [])
 * @method static $this orSearchPhrasePrefix($phrase, mixed $columns = null, $options = [])
 * @method static $this searchNotPhrasePrefix($phrase, mixed $columns = null, $options = [])
 * @method static $this orSearchNotPhrasePrefix($phrase, mixed $columns = null, $options = [])
 *-----------------------------------
 * @method static $this searchBoolPrefix($query, mixed $columns = null, $options = [])
 * @method static $this orSearchBoolPrefix($query, mixed $columns = null, $options = [])
 * @method static $this searchNotBoolPrefix($query, mixed $columns = null, $options = [])
 * @method static $this orSearchNotBoolPrefix($query, mixed $columns = null, $options = [])
 *-----------------------------------
 * @method static $this searchFuzzy($query, mixed $columns = null, $options = [])
 * @method static $this orSearchFuzzy($query, mixed $columns = null, $options = [])
 * @method static $this searchNotFuzzy($query, mixed $columns = null, $options = [])
 * @method static $this orSearchNotFuzzy($query, mixed $columns = null, $options = [])
 *-----------------------------------
 * @method static $this searchFuzzyPrefix($query, mixed $columns = null, $options = [])
 * @method static $this orSearchFuzzyPrefix($query, mixed $columns = null, $options = [])
 * @method static $this searchNotFuzzyPrefix($query, mixed $columns = null, $options = [])
 * @method static $this orSearchNotFuzzyPrefix($query, mixed $columns = null, $options = [])
 *-----------------------------------
 * @method static $this searchQueryString(mixed $query, mixed $columns = null, $options = [])
 * @method static $this orSearchQueryString(mixed $query, mixed $columns = null, $options = [])
 * @method static $this searchNotQueryString(mixed $query, mixed $columns = null, $options = [])
 * @method static $this orSearchNotQueryString(mixed $query, mixed $columns = null, $options = [])
 *===========================================
 * Specialty Methods
 *===========================================
 * @method static $this whereScript(string $script, array $options = [], $boolean = 'and')
 * @method static $this orWhereScript(string $script, array $options = [])
 *-----------------------------------
 * @method static $this highlight($columns = ['*'], $preTag = '<em>', $postTag = '</em>', array $options = [])
 *-----------------------------------
 * @method static $this whereChild(string $documentType, \Closure $callback, $options = [], $boolean = 'and')
 * @method static $this whereParent(string $documentType, \Closure $callback, $options = [], $boolean = 'and')
 * @method static $this whereParentId(string $parentType, $id, string $boolean = 'and')
 *-----------------------------------
 * @method static $this excludeFields(string|array $fields)
 * @method static $this withAnalyzer(string $analyzer)
 * @method static $this withMinScore(float $val)
 * @method static $this withSuffix($suffix)
 * @method static $this withTrackTotalHits(bool|int|null $val = true)
 * @method static $this withoutTrackTotalHits()
 *-----------------------------------
 * @method static $this routing(string $routing)
 * @method static $this parentId(string $id)
 *-----------------------------------
 * @method static $this proceedOnConflicts()
 * @method static $this onConflicts(string $option = 'proceed')
 *-----------------------------------
 * @method static Model withoutRefresh()
 * @method static Model withRefresh(bool|string $refresh)
 * @method static Model withOpType(string $value)
 * @method static Model createOnly()
 *-----------------------------------
 * @method static int push(string $column, $value = null, $unique = false)
 * @method static int pull(string $column, $value = null)
 *-----------------------------------
 * @method static int incrementEach($columns, $extra = [])
 * @method static int decrementEach($columns, $extra = [])
 *===========================================
 * Filter and Order Methods
 *===========================================
 * @method static $this orderBy($column, $direction = 1, array $options = [])
 * @method static $this orderByDesc($column, array $options = [])
 *-----------------------------------
 * @method static $this groupBy(...$groups)
 * @method static $this groupByRanges($column, $ranges = [])
 * @method static $this groupByDateRanges($column, $ranges = [], $options = [])
 *-----------------------------------
 * @method static $this orderByGeo(string $column, array $coordinates, $direction = 1, array $options = [])
 * @method static $this orderByGeoDesc(string $column, array $coordinates, array $options = [])
 *-----------------------------------
 * @method static $this orderByNested(string $column, $direction = 1, string $mode = 'avg')
 * @method static $this orderByNestedDesc(string $column, string $mode = 'avg')
 *-----------------------------------
 * @method static $this withSort(string $column, $key, $value)
 *===========================================
 * Executors
 *===========================================
 * @method static Model|null find($id)
 * @method static Model|null first($columns = ['*'])
 * @method static Model firstOrCreate($attributes, $values = [])
 * @method static Model createOrFail(array $attributes)
 *-----------------------------------
 * @method static array getModels($columns = ['*'])
 * @method static ElasticCollection get($columns = ['*'])
 * @method static MetaDTO|bool insert(array $values)
 * @method static array bulkInsert(array $values)
 * @method static int upsert(array $values, array|string $uniqueBy, ?array $update = null)
 * @method static Model create(array $attributes)
 *-----------------------------------
 * @method static array|string toDsl()
 *-----------------------------------
 * @method static ElasticCollection rawSearch($dslBody, $options = [])
 * @method static array rawAggregation($dslBody, $options = [])
 * @method static array rawDsl($dsl)
 *-----------------------------------
 * @method static LengthAwarePaginator paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
 * @method static SearchAfterPaginator cursorPaginate($perPage = null, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
 *-----------------------------------
 * @method static bool chunk($count, callable $callback, $scrollTimeout = '30s')
 * @method static bool chunkById($count, $callback, $column = '_id', $alias = null)
 *-----------------------------------
 * @method static ElasticCollection distinct(mixed $columns = [], bool $includeCount = false)
 * @method static ElasticCollection bulkDistinct(array $columns = [], bool $includeCount = false)
 *===========================================
 * Aggregation Methods
 *===========================================
 * @method static int|array sum($column, array $options = [])
 * @method static int|array min($column, array $options = [])
 * @method static int|array max($column, array $options = [])
 * @method static int|array avg($column, array $options = [])
 * @method static mixed agg(array $functions, string|array $columns, array $options = [])
 * @method static mixed aggregate($function, $columns = ['*'], $options = [])
 *-----------------------------------
 * @method static mixed boxplot($columns, $options = [])
 * @method static mixed cardinality($columns, $options = [])
 * @method static mixed extendedStats($columns, $options = [])
 * @method static mixed matrix($columns, $options = [])
 * @method static mixed medianAbsoluteDeviation($columns, $options = [])
 * @method static mixed percentiles($columns, $options = [])
 * @method static mixed stats($columns, $options = [])
 * @method static mixed stringStats($columns, $options = [])
 *-----------------------------------
 * @method static array getAggregationResults()
 * @method static array getRawAggregationResults()
 * @method static mixed getGroupByAfterKey($offset)
 *===========================================
 * Index Methods
 *===========================================
 * @method static void truncate()
 * @method static bool indexExists()
 * @method static void deleteIndexIfExists()
 * @method static void deleteIndex()
 * @method static bool createIndex(?\Closure $callback = null)
 * @method static array getIndexMappings(bool $raw = false)
 * @method static array getFieldMappings(bool $raw = false)
 * @method static array getFieldMapping(string|array $field = '*', bool $raw = false)
 * @method static array getIndexSettings()
 * @method static bool hasField(string $column)
 * @method static bool hasFields(array $columns)
 *
 * @property object $search_highlights
 * @property object $with_highlights
 * @property array $search_highlights_as_array
 *
 * @mixin Builder
 */
trait ModelDocs {}

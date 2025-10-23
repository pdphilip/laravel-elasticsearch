<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent\Docs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Cursor;
use PDPhilip\Elasticsearch\Eloquent\Builder;
use PDPhilip\Elasticsearch\Eloquent\ElasticCollection;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Pagination\SearchAfterPaginator;

/**
 * Query Builder Methods ---------------------------------
 *
 * @method static $this query()
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
 * @method static $this orWhereNotTermsExists($column)
 *-----------------------------------
 * @method static $this whereRaw($dsl, $bindings = [], $boolean = 'and', $options = [])
 *===========================================
 * Full Text Search Methods
 *===========================================
 * @method static $this searchTerm($term, $fields = ['*'], $options = [], $boolean = 'and')
 * @method static $this orSearchTerm($term, $fields = ['*'], $options = [])
 * @method static $this searchNotTerm($term, $fields = ['*'], $options = [])
 * @method static $this orSearchNotTerm($term, $fields = ['*'], $options = [])
 *-----------------------------------
 * @method static $this searchTermMost($term, $fields = ['*'], $options = [], $boolean = 'and')
 * @method static $this orSearchTermMost($term, $fields = ['*'], $options = [])
 * @method static $this searchNotTermMost($term, $fields = ['*'], $options = [])
 * @method static $this orSearchNotTermMost($term, $fields = ['*'], $options = [])
 *-----------------------------------
 * @method static $this searchTermCross($term, $fields = ['*'], $options = [], $boolean = 'and')
 * @method static $this orSearchTermCross($term, $fields = ['*'], $options = [])
 * @method static $this searchNotTermCross($term, $fields = ['*'], $options = [])
 * @method static $this orSearchNotTermCross($term, $fields = ['*'], $options = [])
 *-----------------------------------
 * @method static $this searchPhrase($phrase, $fields = ['*'], $options = [], $boolean = 'and')
 * @method static $this orSearchPhrase($phrase, $fields = ['*'], $options = [])
 * @method static $this searchNotPhrase($phrase, $fields = ['*'], $options = [])
 * @method static $this orSearchNotPhrase($phrase, $fields = ['*'], $options = [])
 *-----------------------------------
 * @method static $this searchPhrasePrefix($phrase, $fields = ['*'], $options = [], $boolean = 'and')
 * @method static $this orSearchPhrasePrefix($phrase, $fields = ['*'], $options = [])
 * @method static $this searchNotPhrasePrefix($phrase, $fields = ['*'], $options = [])
 * @method static $this orSearchNotPhrasePrefix($phrase, $fields = ['*'], $options = [])
 *-----------------------------------
 * @method static $this searchBoolPrefix($phrase, $fields = ['*'], $options = [], $boolean = 'and')
 * @method static $this orSearchBoolPrefix($phrase, $fields = ['*'], $options = [])
 *-----------------------------------
 * @method static $this searchFuzzy($term, $fields = ['*'], $options = [])
 * @method static $this orSearchFuzzy($term, $fields = ['*'], $options = [])
 * @method static $this searchNotFuzzy($term, $fields = ['*'], $options = [])
 * @method static $this orSearchNotFuzzy($term, $fields = ['*'], $options = [])
 *-----------------------------------
 * @method static $this searchFuzzyPrefix($term, $fields = ['*'], $options = [])
 * @method static $this orSearchFuzzyPrefix($term, $fields = ['*'], $options = [])
 * @method static $this searchNotFuzzyPrefix($term, $fields = ['*'], $options = [])
 * @method static $this orSearchNotFuzzyPrefix($term, $fields = ['*'], $options = [])
 *                                                                                    -----------------------------------
 * @method static $this searchQueryString($query, $fields = '*', $options = [])
 * @method static $this orSearchQueryString($query, $fields = '*', $options = [])
 * @method static $this searchNotQueryString($query, $fields = '*', $options = [])
 * @method static $this orSearchNotQueryString($query, $fields = '*', $options = [])
 *===========================================
 * Speciality methods
 *===========================================
 * @method static $this whereScript($script, $options = [], $boolean = 'and')
 * @method static $this orWhereScript($script, $options = [])
 *-----------------------------------
 * @method static $this highlight($fields = [], $preTag = '<em>', $postTag = '</em>', $globalOptions = [])
 * @method static $this withoutRefresh()
 *-----------------------------------
 * @method static $this whereChild($documentType, $callback, $options = [], $boolean = 'and')
 * @method static $this whereParent($documentType, $callback, $options = [], $boolean = 'and')
 * @method static $this whereParentId($parentType, $id, $boolean = 'and')
 *-----------------------------------
 * @method static $this excludeFields($fields)
 * @method static $this withAnalyzer($analyzer)
 * @method static $this withMinScore($val)
 * @method static $this withSuffix($suffix)
 *-----------------------------------
 * @method static $this routing($routing)
 * @method static $this parentId($id)
 *-----------------------------------
 * @method static $this proceedOnConflicts()
 * @method static $this onConflicts($option = 'proceed')
 *-----------------------------------
 * @method static $this push($column, $value = null, $unique = false)
 * @method static $this pull($column, $value = null)
 *-----------------------------------
 * @method static $this incrementEach($columns, $extra = [])
 * @method static $this decrementEach($columns, $extra = [])
 *===========================================
 * Filter and order methods
 *===========================================
 * @method static $this orderBy($column, $direction = 'asc',$options = [])
 * @method static $this orderByDesc($column,$options = [])
 *-----------------------------------
 * @method static $this groupBy($groups)
 *-----------------------------------
 * @method static $this orderByGeo($column, $pin, $direction = 'asc', $options = [])
 * @method static $this orderByGeoDesc($column, $pin, $options = [])
 *-----------------------------------
 * @method static $this orderByNested($column, $direction = 'asc', $mode = null)
 * @method static $this orderByNestedDesc($column, $direction = 'asc', $mode = null)
 *-----------------------------------
 * @method static $this withSort($column, $key, $value)
 *===========================================
 * Executors
 *===========================================
 * @method static Model|null find($id)
 * @method static Model|null first($columns = ['*'])
 * @method static Model firstOrCreate($attributes, $values = [])
 *-----------------------------------
 * @method static array getModels($columns = ['*'])
 * @method static ElasticCollection get($columns = ['*'])
 * @method static ElasticCollection insert($values, $returnData = null)
 * @method static self createOnly()
 * @method static self createOrFail(array $attributes)
 *-----------------------------------
 * @method static array toDsl($columns = ['*'])
 * @method static array toSql($columns = ['*'])
 *-----------------------------------
 * @method static mixed rawDsl($bodyParams)
 * @method static ElasticCollection rawSearch($bodyParams)
 * @method static array rawAggregation($bodyParams)
 *-----------------------------------
 * @method static LengthAwarePaginator paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null,  $total = null)
 * @method static SearchAfterPaginator cursorPaginate($perPage = null, $columns = [], $cursorName = 'cursor',  $cursor = null)
 *-----------------------------------
 * @method static bool chunk($count, $callback)
 * @method static bool chunkById($count, $callback, $column = '_id', $alias = null)
 *                                                                                  -----------------------------------
 * @method static ElasticCollection distinct($columns = [], $includeCount = false)
 *===========================================
 * Aggregators Methods
 *===========================================
 * @method static int|array sum($columns)
 * @method static int|array min($columns)
 * @method static int|array max($columns)
 * @method static int|array avg($columns)
 * @method static mixed agg(array $functions, $column)
 *-----------------------------------
 * @method static mixed boxplot($columns, $options = [])
 * @method static mixed cardinality($columns, $options = [])
 * @method static mixed extendedStats($columns, $options = [])
 * @method static mixed matrix($columns, $options = [])
 * @method static mixed medianAbsoluteDeviation($columns, $options = [])
 * @method static mixed percentiles($columns, $options = [])
 * @method static mixed stats($columns, $options = [])
 * @method static mixed stringStats( $columns, $options = [])
 *-----------------------------------
 * @method static array getAggregationResults()
 * @method static array getRawAggregationResults()
 *===========================================
 * Index Methods
 *===========================================
 * @method static void truncate()
 * @method static bool indexExists()
 * @method static bool deleteIndexIfExists()
 * @method static bool deleteIndex()
 * @method static bool createIndex($callback)
 * @method static array getIndexMappings()
 * @method static array getFieldMapping(string|array $field = '*', $raw = false)
 * @method static array getIndexOptions()
 *
 * @property object $search_highlights
 * @property object $with_highlights
 * @property array $search_highlights_as_array
 *
 * @mixin Builder
 */
trait ModelDocs {}

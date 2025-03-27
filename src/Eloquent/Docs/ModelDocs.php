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
 * @method static Builder query()
 * @method static $this where($column, $operator = null, $value = null, $boolean = 'and', $options = [])
 * @method static $this whereNot($column, $operator = null, $value = null, $boolean = 'and', $options = [])
 * @method static $this orWhere($column, $operator = null, $value = null, $options = [])
 * @method static $this orWhereNot($column, $operator = null, $value = null, $options = [])
 * @method static $this whereRaw($dsl, $bindings = [], $boolean = 'and', $options = [])
 * @method static $this whereIn($column, $values, $boolean = 'and', $not = false, $options = [])
 * @method static $this whereNotIn($column, $values, $boolean = 'and', $options = [])
 * @method static $this orWhereIn($column, $values, $options = [])
 * @method static $this orWhereNotIn($column, $values, $options = [])
 * @method static $this whereNull($columns, $boolean = 'and', $not = false)
 * @method static $this whereNotNull($columns, $boolean = 'and')
 * @method static $this orWhereNull($columns)
 * @method static $this orWhereNotNull($columns)
 * @method static $this whereExact($column, $value, $boolean = 'and', $not = false, $options = [])
 * @method static $this whereNotExact($column, $value, $options = [])
 * @method static $this orWhereExact($column, $value, $options = [])
 * @method static $this orWhereNotExact($column, $value, $options = [])
 * @method static $this whereTermExists($column, $boolean = 'and', $not = false)
 * @method static $this whereNotTermExists($column)
 * @method static $this orWhereTermExists($column)
 * @method static $this orWhereNotTermsExists($column)
 * @method static $this whereFuzzy($column, $value, $boolean = 'and', $not = false, $options = [])
 * @method static $this whereNotFuzzy($column, $value, $options = [])
 * @method static $this orWhereFuzzy($column, $value, $options = [])
 * @method static $this orWhereNotFuzzy($column, $value, $options = [])
 * @method static $this wherePrefix($column, $value, $boolean = 'and', $not = false, $options = [])
 * @method static $this whereNotPrefix($column, $value, $options = [])
 * @method static $this orWherePrefix($column, $value, $options = [])
 * @method static $this orWhereNotPrefix($column, $value, $options = [])
 * @method static $this whereGeoBox($field, array $topLeft, array $bottomRight, $validationMethod = null, $boolean = 'and', bool $not = false)
 * @method static $this whereNotGeoBox($field, array $topLeft, array $bottomRight, $validationMethod = null)
 * @method static $this orWhereGeoBox($field, array $topLeft, array $bottomRight, $validationMethod = null)
 * @method static $this orWhereNotGeoBox($field, array $topLeft, array $bottomRight, $validationMethod = null)
 * @method static $this whereGeoDistance($field, string $distance, array $location, $distanceType = null, $validationMethod = null, $boolean = 'and', bool $not = false)
 * @method static $this whereNotGeoDistance($field, string $distance, array $location, $distanceType = null, $validationMethod = null)
 * @method static $this orWhereGeoDistance($field, string $distance, array $location, $distanceType = null, $validationMethod = null)
 * @method static $this orWhereNotGeoDistance($field, string $distance, array $location, $distanceType = null, $validationMethod = null)
 * @method static $this whereNestedObject($column, $query, $filterInnerHits = false, $options = [], $boolean = 'and', $not = false)
 * @method static $this whereNotNestedObject($column, $query, $filterInnerHits = false, $options = [])
 * @method static $this orWhereNestedObject($column, $query, $filterInnerHits = false, $options = [])
 * @method static $this orWhereNotNestedObject($column, $query, $filterInnerHits = false, $options = [])
 * @method static $this wherePhrase(string $column, string $value, $boolean = 'and', $options = [])
 * @method static $this wherePhrasePrefix(string $column, string $value, $boolean = 'and', $options = [])
 * @method static $this whereDate($column, $operator = null, $value = null, $boolean = 'and', $options = [])
 * @method static $this whereTimestamp($column, $operator = null, $value = null, $boolean = 'and', $options = [])
 * @method static $this whereRegex(string $column, string $regex, $options = [])
 * @method static $this orWherePhrase(string $column, string $value, $options = [])
 * @method static $this orWherePhrasePrefix(string $column, string $value, $options = [])
 * @method static $this orWhereDate($column, $operator = null, $value = null, $options = [])
 * @method static $this orWhereTimestamp($column, $operator = null, $value = null, $options = [])
 * @method static $this orWhereRegex(string $column, string $regex, $options = [])
 *
 * Filter and order methods ---------------------------------
 * @method static $this orderBy(string $column, string $direction = 'asc')
 * @method static $this orderByDesc(string $column)
 * @method static $this withSort(string $column, string $key, mixed $value)
 * @method static $this orderByGeo(string $column, array $pin, $direction = 'asc', $unit = 'km', $mode = null, $type = 'arc')
 * @method static $this orderByGeoDesc(string $column, array $pin, $unit = 'km', $mode = null, $type = 'arc')
 * @method static $this orderByNested(string $column, string $direction = 'asc', string $mode = null)
 * @method static $this filterGeoBox(string $column, array $topLeftCoords, array $bottomRightCoords)
 * @method static $this filterGeoPoint(string $column, string $distance, array $point)
 *
 * Full Text Search Methods ---------------------------------
 * @method static $this searchFor($value, $fields = ['*'], $options = [], $boolean = 'and')
 * @method static $this searchTerm($term, $fields = ['*'], $options = [], $boolean = 'and')
 * @method static $this searchTermMost($term, $fields = ['*'], $options = [], $boolean = 'and')
 * @method static $this searchTermCross($term, $fields = ['*'], $options = [], $boolean = 'and')
 * @method static $this searchPhrase($phrase, $fields = ['*'], $options = [], $boolean = 'and')
 * @method static $this searchPhrasePrefix($phrase, $fields = ['*'], $options = [], $boolean = 'and')
 * @method static $this searchBoolPrefix($phrase, $fields = ['*'], $options = [], $boolean = 'and')
 * @method static $this orSearchFor($value, $fields = ['*'], $options = [])
 * @method static $this orSearchTerm($term, $fields = ['*'], $options = [])
 * @method static $this orSearchTermMost($term, $fields = ['*'], $options = [])
 * @method static $this orSearchTermCross($term, $fields = ['*'], $options = [])
 * @method static $this orSearchPhrase($phrase, $fields = ['*'], $options = [])
 * @method static $this orSearchPhrasePrefix($phrase, $fields = ['*'], $options = [])
 * @method static $this orSearchBoolPrefix($phrase, $fields = ['*'], $options = [])
 * @method static $this highlight(array $fields = [], string|array $preTag = '<em>', string|array $postTag = '</em>', array $globalOptions = [])
 * @method static $this withoutRefresh()
 *
 * Query Executors --------------------------------------------
 * @method static Model|null find($id)
 * @method static array getModels(array $columns = ['*'])
 * @method static ElasticCollection get(array $columns = ['*'])
 * @method static Model|null first(array $columns = ['*'])
 * @method static Model firstOrCreate(array $attributes, array $values = [])
 * @method static int|array sum(array|string $columns)
 * @method static int|array min(array|string $columns)
 * @method static int|array max(array|string $columns)
 * @method static int|array avg(array|string $columns)
 * @method static mixed agg(array $functions, $column)
 * @method static LengthAwarePaginator paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null, ?int $total = null)
 * @method static SearchAfterPaginator cursorPaginate(int|null $perPage = null, array $columns = [], string $cursorName = 'cursor', ?Cursor $cursor = null)
 * @method static ElasticCollection insert($values, $returnData = null):
 * @method static array toDsl(array $columns = ['*'])
 * @method static array toSql(array $columns = ['*'])
 * @method static mixed rawDsl(array $bodyParams)
 * @method static ElasticCollection rawSearch(array $bodyParams)
 * @method static array rawAggregation(array $bodyParams)
 * @method static bool chunk(mixed $count, callable $callback, string $keepAlive = '5m')
 * @method static bool chunkById(mixed $count, callable $callback, $column = '_id', $alias = null, $keepAlive = '5m')
 *
 * Index Methods ---------------------------------
 * @method static void truncate()
 * @method static bool indexExists()
 * @method static bool deleteIndexIfExists()
 * @method static bool deleteIndex()
 * @method static bool createIndex(array $options = [])
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

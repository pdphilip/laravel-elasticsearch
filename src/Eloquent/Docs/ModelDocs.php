<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent\Docs;

use Closure;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Cursor;
use PDPhilip\Elasticsearch\Collection\ElasticCollection;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Pagination\SearchAfterPaginator;
use PDPhilip\Elasticsearch\Query\Builder;

/**
 * Query Builder Methods ---------------------------------
 *
 * @method static $this query()
 * @method static $this where(array|Closure|Expression|string $column, $operator = null, $value = null, $boolean = 'and')
 * @method static $this whereIn(string $column, array $values)
 * @method static $this whereExact(string $column, string $value, $boolean = 'and')
 * @method static $this wherePhrase(string $column, string $value, $boolean = 'and')
 * @method static $this wherePhrasePrefix(string $column, string $value, $boolean = 'and')
 * @method static $this whereDate($column, $operator = null, $value = null, $boolean = 'and')
 * @method static $this whereTimestamp($column, $operator = null, $value = null, $boolean = 'and')
 * @method static $this whereRegex(string $column, string $regex)
 * @method static $this orWhere(array|Closure|Expression|string $column, $operator = null, $value = null)
 * @method static $this orWhereIn(string $column, array $values)
 * @method static $this orWhereExact(string $column, string $value)
 * @method static $this orWherePhrase(string $column, string $value)
 * @method static $this orWherePhrasePrefix(string $column, string $value)
 * @method static $this orWhereDate($column, $operator = null, $value = null)
 * @method static $this orWhereTimestamp($column, $operator = null, $value = null)
 * @method static $this orWhereRegex(string $column, string $regex)
 * @method static $this whereNestedObject(string $column, Callable $callback, string $scoreType = 'avg')
 * @method static $this whereNotNestedObject(string $column, Callable $callback, string $scoreType = 'avg')
 * @method static $this queryNested(string $column, Callable $callback)
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
 * @method static $this withHighlights(array $fields = [], string|array $preTag = '<em>', string|array $postTag = '</em>', array $globalOptions = [])
 * @method static $this asFuzzy(?int $depth = null)
 * @method static $this setMinShouldMatch(int $value)
 *
 * Query Executors --------------------------------------------
 * @method static Model|null find($id)
 * @method static array getModels(array $columns = ['*'])
 * @method static ElasticCollection get(array $columns = ['*'])
 * @method static Model|null first(array $columns = ['*'])
 * @method static Model firstOrCreate(array $attributes, array $values = [])
 * @method static Model firstOrCreateWithoutRefresh(array $attributes, array $values = [])
 * @method static int|array sum(array|string $columns)
 * @method static int|array min(array|string $columns)
 * @method static int|array max(array|string $columns)
 * @method static int|array avg(array|string $columns)
 * @method static mixed agg(array $functions, $column)
 * @method static LengthAwarePaginator paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null, ?int $total = null)
 * @method static SearchAfterPaginator cursorPaginate(int|null $perPage = null, array $columns = [], string $cursorName = 'cursor', ?Cursor $cursor = null)
 * @method static ElasticCollection insert($values, $returnData = null):
 * @method static ElasticCollection insertWithoutRefresh($values, $returnData = null)
 * @method static array toDsl(array $columns = ['*'])
 * @method static array rawSearch(array $bodyParams, bool $returnRaw = false)
 * @method static array rawAggregation(array $bodyParams)
 * @method static bool chunk(mixed $count, callable $callback, string $keepAlive = '5m')
 * @method static bool chunkById(mixed $count, callable $callback, $column = '_id', $alias = null, $keepAlive = '5m')
 *
 * Index Methods ---------------------------------
 * @method static string getQualifiedKeyName()
 * @method static string getConnection()
 * @method static void truncate()
 * @method static bool indexExists()
 * @method static bool deleteIndexIfExists()
 * @method static bool deleteIndex()
 * @method static bool createIndex(array $options = [])
 * @method static array getIndexMappings()
 * @method static array getIndexOptions()
 *
 * Search Methods - Due for sunsetting, keep for now
 * @method static $this term(string $term, $boostFactor = null)
 * @method static $this andTerm(string $term, $boostFactor = null)
 * @method static $this orTerm(string $term, $boostFactor = null)
 * @method static $this fuzzyTerm(string $term, $boostFactor = null)
 * @method static $this andFuzzyTerm(string $term, $boostFactor = null)
 * @method static $this orFuzzyTerm(string $term, $boostFactor = null)
 * @method static $this regEx(string $term, $boostFactor = null)
 * @method static $this andRegEx(string $term, $boostFactor = null)
 * @method static $this orRegEx(string $term, $boostFactor = null)
 * @method static $this phrase(string $term, $boostFactor = null)
 * @method static $this andPhrase(string $term, $boostFactor = null)
 * @method static $this orPhrase(string $term, $boostFactor = null)
 * @method static $this minShouldMatch(int $value)
 * @method static $this highlight(array $fields = [], string|array $preTag = '<em>', string|array $postTag = '</em>', $globalOptions = [])
 * @method static $this minScore(float $value)
 * @method static $this field(string $field, int $boostFactor = null)
 * @method static $this fields(array $fields)
 * @method static array searchModels(array $columns = ['*'])
 * @method static ElasticCollection search(array $columns = ['*'])
 *
 * @property object $search_highlights
 * @property object $with_highlights
 * @property array $search_highlights_as_array
 *
 * @mixin Builder
 */
trait ModelDocs {}

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
 * @method static $this minScore(float $value)
 * @method static $this field(string $field, int $boostFactor = null)
 * @method static $this fields(array $fields)
 * @method static int|array sum(array|string $columns)
 * @method static int|array min(array|string $columns)
 * @method static int|array max(array|string $columns)
 * @method static int|array avg(array|string $columns)
 * @method static array getModels(array $columns = ['*'])
 * @method static array searchModels(array $columns = ['*'])
 * @method static ElasticCollection get(array $columns = ['*'])
 * @method static Model|null first(array $columns = ['*'])
 * @method static ElasticCollection search(array $columns = ['*'])
 * @method static array toDsl(array $columns = ['*'])
 * @method static mixed agg(array $functions, $column)
 * @method static $this where(array|Closure|Expression|string $column, $operator = null, $value = null, $boolean = 'and')
 * @method static $this whereDate($column, $operator = null, $value = null, $boolean = 'and')
 * @method static $this whereTimestamp($column, $operator = null, $value = null, $boolean = 'and')
 * @method static $this whereIn(string $column, array $values)
 * @method static $this whereExact(string $column, string $value)
 * @method static $this wherePhrase(string $column, string $value)
 * @method static $this wherePhrasePrefix(string $column, string $value)
 * @method static $this filterGeoBox(string $column, array $topLeftCoords, array $bottomRightCoords)
 * @method static $this filterGeoPoint(string $column, string $distance, array $point)
 * @method static $this whereRegex(string $column, string $regex)
 * @method static $this whereNestedObject(string $column, Callable $callback, string $scoreType = 'avg')
 * @method static $this whereNotNestedObject(string $column, Callable $callback, string $scoreType = 'avg')
 * @method static $this firstOrCreate(array $attributes, array $values = [])
 * @method static $this firstOrCreateWithoutRefresh(array $attributes, array $values = [])
 * @method static $this orderBy(string $column, string $direction = 'asc')
 * @method static $this orderByDesc(string $column)
 * @method static $this withSort(string $column, string $key, mixed $value)
 * @method static $this orderByGeo(string $column, array $pin, $direction = 'asc', $unit = 'km', $mode = null, $type = 'arc')
 * @method static $this orderByGeoDesc(string $column, array $pin, $unit = 'km', $mode = null, $type = 'arc')
 * @method static $this orderByNested(string $column, string $direction = 'asc', string $mode = null)
 * @method static bool chunk(mixed $count, callable $callback, string $keepAlive = '5m')
 * @method static bool chunkById(mixed $count, callable $callback, $column = '_id', $alias = null, $keepAlive = '5m')
 * @method static $this queryNested(string $column, Callable $callback)
 * @method static array rawSearch(array $bodyParams, bool $returnRaw = false)
 * @method static array rawAggregation(array $bodyParams)
 * @method static $this highlight(array $fields = [], string|array $preTag = '<em>', string|array $postTag = '</em>', $globalOptions = [])
 * @method static bool deleteIndexIfExists()
 * @method static bool deleteIndex()
 * @method static bool createIndex(array $settings = [])
 * @method static array getIndexMappings()
 * @method static array getIndexSettings()
 * @method static bool indexExists()
 * @method static LengthAwarePaginator paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null, ?int $total = null)
 * @method static SearchAfterPaginator cursorPaginate(int|null $perPage = null, array $columns = [], string $cursorName = 'cursor', ?Cursor $cursor = null)
 * @method static string getQualifiedKeyName()
 * @method static string getConnection()
 * @method static void truncate()
 * @method static ElasticCollection insert($values, $returnData = null):
 * @method static ElasticCollection insertWithoutRefresh($values, $returnData = null)
 *
 * @property object $search_highlights
 * @property object $with_highlights
 * @property array $search_highlights_as_array
 *
 * @mixin Builder
 */
trait ModelDocs {}

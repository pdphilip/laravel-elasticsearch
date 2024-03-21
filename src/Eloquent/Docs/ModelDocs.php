<?php

namespace PDPhilip\Elasticsearch\Eloquent\Docs;

/**
 * @method static $this term(string $term, $boostFactor = null) @return $this
 * @method $this andTerm(string $term, $boostFactor = null)
 * @method $this orTerm(string $term, $boostFactor = null)
 * @method static $this fuzzyTerm(string $term, $boostFactor = null)
 * @method $this andFuzzyTerm(string $term, $boostFactor = null)
 * @method $this orFuzzyTerm(string $term, $boostFactor = null)
 * @method static $this regEx(string $term, $boostFactor = null)
 * @method $this andRegEx(string $term, $boostFactor = null)
 * @method $this orRegEx(string $term, $boostFactor = null)
 * @method $this minShouldMatch(int $value)
 * @method $this minScore(float $value)
 * @method $this field(string $field, int $boostFactor = null)
 * @method $this fields(array $fields)
 * @method search(array $columns = '*')
 * @method query(array $columns = '*')
 *
 * @method  $this whereIn(string $column, array $values)
 * @method  $this whereExact(string $column, string $value)
 * @method  $this wherePhrase(string $column, string $value)
 * @method  $this filterGeoBox(string $column, array $topLeftCoords, array $bottomRightCoords)
 * @method  $this filterGeoPoint(string $column, string $distance, array $point)
 * @method  $this whereRegex(string $column, string $regex)
 * @method  $this whereNestedObject(string $column, Callable $callback, string $scoreType = 'avg')
 * @method  $this whereNotNestedObject(string $column, Callable $callback, string $scoreType = 'avg')
 * @method  $this firstOrCreate(array $attributes, array $values = [])
 * @method  $this firstOrCreateWithoutRefresh(array $attributes, array $values = [])
 * @method  $this  deleteIndexIfExists()
 *
 *
 * @mixin \Illuminate\Database\Query\Builder
 */
trait ModelDocs
{
}

# Changelog

All notable changes to this `laravel-elasticsearch` package will be documented in this file.

## v5.4.0 - 2026-02-15

### Added
- `TimeOrderedUUIDGenerator` — sortable 20-character IDs where lexicographic order matches chronological order across processes (millisecond granularity)
- `GeneratesTimeOrderedIds` trait with `getRecordTimestamp()` and `getRecordDate()` helpers — safe for mixed datasets (returns null for pre-existing non-time-ordered IDs)
- New test coverage: advanced aggregations, filter context queries, DSL output inspection, point-in-time pagination, multi-match search, time-ordered IDs

### Changed
- Refactored Query Builder into focused concerns: `BuildsAggregations`, `BuildsSearchQueries`, `BuildsFieldQueries`, `BuildsGeoQueries`, `BuildsNestedQueries`, `HandlesScripts`, `ManagesPit`
- Refactored Grammar into concerns: `CompilesAggregations`, `CompilesOrders`, `CompilesWheres`, `FieldUtilities`
- Added `declare(strict_types=1)` to all ID generation classes and traits
- Consolidated test ID strategy into `TestsWithIdStrategies` trait (removed duplicate `WithIds/` and `IdGenerated/` directories)

### Fixed
- `id` is now always present in serialized model output
- Removed dead debug code from Connection.php

## v5.3.0 - 2026-01-20

This release is compatible with Laravel 10, 11 & 12

### New features

#### Distinct with Relations

`distinct()` queries now return [ElasticCollections](https://elasticsearch.pdphilip.com/eloquent/the-base-model#elastic-collections);

If a model relation exists and the  **aggregation is done on the foreign key**, you can load the related model

```php
UserLog::where('created_at', '>=', Carbon::now()->subDays(30))
    ->with('user')
    ->orderByDesc('_count')
    ->select('user_id')
    ->distinct(true);


```
Why: You can now treat distinct aggregations like real Eloquent results, including relationships.

#### Bulk Distinct Queries

New query method `bulkDistinct(array $fields, $includeDocCount = false)` - [Docs](https://elasticsearch.pdphilip.com/eloquent/distinct/#bulk-distinct)

Run multiple distinct aggregations **in parallel** within a single Elasticsearch query.

```php
$top3 = UserSession::where('created_at', '>=', Carbon::now()->subDays(30))
    ->limit(3)
    ->bulkDistinct(['country', 'device', 'browser_name'], true);

```
Why: Massive performance gains vs running sequential distinct queries.

#### Group By Ranges

`groupByRanges()` performs a [range aggregation](https://www.elastic.co/docs/reference/aggregations/search-aggregations-bucket-range-aggregation) on the specified field. - [Docs](https://elasticsearch.pdphilip.com/eloquent/distinct/#groupby-ranges)

`groupByRanges()->get()`  — return bucketed results - [Docs](https://elasticsearch.pdphilip.com/eloquent/distinct/#groupby-ranges)

`groupByRanges()->agg()` -  apply metric aggregations per bucket -[Docs](https://elasticsearch.pdphilip.com/eloquent/distinct/#groupby-ranges-with-aggregations)

#### Group By Date Ranges

`groupByDateRanges()` performs a [date range aggregation](https://www.elastic.co/docs/reference/aggregations/search-aggregations-bucket-daterange-aggregation) on the specified field. - [Docs](https://elasticsearch.pdphilip.com/eloquent/distinct/#groupby-date-ranges)

`groupByDateRanges()->get()` — bucketed date ranges

`groupByDateRanges()->agg()` — metrics per date bucket

#### Model Meta Accessor

New model method `getMetaValue($key)` -  [Docs](https://elasticsearch.pdphilip.com/eloquent/the-base-model/#get-model-meta-value)

Convenience method to get a specific meta value from the model instance.

```php
$product = Product::where('color', 'green')->first();
$score = $product->getMetaValue('score');

```
#### Bucket Values in Meta

When a bucketed query is executed, the raw bucket data is now stored in model meta. -[Docs](https://elasticsearch.pdphilip.com/eloquent/distinct/#raw-bucket-values-from-meta)

```php
$products = Product::distinct('price');
$buckets = $products->map(function ($product) {
    return $product->getMetaValue('bucket');
});

```
**Full Changelog**: https://github.com/pdphilip/laravel-elasticsearch/compare/v5.2.0...v5.3.0

## v5.2.0 - 2025-10-24

This release is compatible with Laravel 10, 11 & 12

### New Feature: Query String Queries

This release introduces Query String Queries, bringing full Elasticsearch `query_string` syntax support directly into your Eloquent-style queries.

- Method: `searchQueryString(query, $fields = null, $options = [])` and related methods (`orSearchQueryString`, `searchNotQueryString`, etc.)
- Supports all `query_string` features — logical operators, wildcards, fuzziness, ranges, regex, boosting, field scoping, and more
- Includes a dedicated `QueryStringOptions` class for fluent option configuration or array-based parameters
- [See Tests](https://github.com/pdphilip/laravel-elasticsearch/blob/main/tests/QueryStringTest.php)
- [Full documentation](https://elasticsearch.pdphilip.com/eloquent/query-string-queries/)

Example:

```php
Product::searchQueryString('status:(active OR pending) name:(full text search)^2')->get();
Product::searchQueryString('price:[5 TO 19}')->get();

// vanilla optional, +pizza required, -ice forbidden
Product::searchQueryString('vanilla +pizza -ice', function (QueryStringOptions $options) {
    $options->type('cross_fields')->fuzziness(2);
})->get();

//etc


```
### Ordering enhancement: unmapped_type

- You can now add an `unmapped_type` flag to your ordering query #88

```php
Product::query()->orderBy('name', 'desc', ['unmapped_type' => 'keyword'])->get();


```
### Bugfix

- Fixed issue where limit values were being reset on bucket aggregations #84

**Full Changelog**: https://github.com/pdphilip/laravel-elasticsearch/compare/v5.1.0...v5.2.0

## v5.1.0 - 2025-08-20

This release is compatible with Laravel 10, 11 & 12

#### 1. New feature, `withTrackTotalHits(bool|int|null $val = true)`

Appends the `track_total_hits` parameter to the DSL query, setting value to `true` will count all the hits embedded in the query meta not capping to Elasticsearch default of 10k hits

```php
$products = Product::limit(5)->withTrackTotalHits(true)->get();
$totalHits = $products->getQueryMeta()->getTotalHits();



```
This can be set by default for all queries by updating the connection config in `database.php`:

```php
'elasticsearch' => [
    'driver' => 'elasticsearch',
    .....
    'options' => [
        'track_total_hits' => env('ES_TRACK_TOTAL_HITS', null),
        ....
    ],
],



```
#### 2. New feature, `createOrFail(array $attributes)`

By default, when using `create($attributes)` where `$attributes `has an `id` that exists, the operation will upsert. `createOrFail` will throw a `BulkInsertQueryException` with status code `409` if the `id` exists

```php
Product::createOrFail([
    'id' => 'some-existing-id',
    'name' => 'Blender',
    'price' => 30,
]);



```
#### 3. New feature `withRefresh(bool|string $refresh)`

By default, inserting documents will wait for the shards to refresh, ie: `withRefresh(true)`, you can set the refresh flag with the following (as per ES docs):

- `true` (default)
  Refresh the relevant primary and replica shards (not the whole index) immediately after the operation occurs, so that the updated document appears in search results immediately.
- `wait_for`
  Wait for the changes made by the request to be made visible by a refresh before replying. This doesn’t force an immediate refresh, rather, it waits for a refresh to happen.
- `false`
  Take no refresh-related actions. The changes made by this request will be made visible at some point after the request returns.

```php
Product::withRefresh('wait_for')->create([
    'name' => 'Blender',
    'price' => 30,
]);



```
### PRS

* Add withTrackTotalHits method to Builder class to add track_total_hits by @caufab in https://github.com/pdphilip/laravel-elasticsearch/pull/76
* feat(query): add op_type=create support and dedupe helpers by @abkrim in https://github.com/pdphilip/laravel-elasticsearch/pull/79

### Bugfix

* Laravel ^12.23 Compatibility - close [#81](https://github.com/pdphilip/laravel-elasticsearch/issues/81)

### New Contributors

* @caufab made their first contribution in https://github.com/pdphilip/laravel-elasticsearch/pull/76

**Full Changelog**: https://github.com/pdphilip/laravel-elasticsearch/compare/v5.0.7...v5.1.0

## v5.0.7 - 2025-07-13

This release is compatible with Laravel 10, 11 & 12

### What's Changed

* Connection bug fix by @pdphilip in https://github.com/pdphilip/laravel-elasticsearch/pull/75 - close #70

**Full Changelog**: https://github.com/pdphilip/laravel-elasticsearch/compare/v5.0.6...v5.0.7

## v5.0.6 - 2025-06-04

This release is compatible with Laravel 10, 11 & 12

### What's Changed

* Bug fix: Chunking `$count` value fixed for setting query limit correctly, via #68

**Full Changelog**: https://github.com/pdphilip/laravel-elasticsearch/compare/v5.0.5...v5.0.6

## v5.0.5 - 2025-05-19

This release is compatible with Laravel 10, 11 & 12

### What's Changed

* Merging in bug fixes  by @use-the-fork in https://github.com/pdphilip/laravel-elasticsearch/pull/65
* Updated outstanding tests
* Fixed bug in  relations`has()` method

**Full Changelog**: https://github.com/pdphilip/laravel-elasticsearch/compare/v5.0.4...v5.0.5

## v5.0.4 - 2025-04-12

This release is compatible with Laravel 10, 11 & 12

### What's changed

- Connection `disconnect()` resets connection - removing connection is unnecessary in the context of Elasticsearch. Issue #64
- Added `getTotalHits()` helper method from query meta
- Bug fix:  `searchFuzzy()` parses options as a closure
- Minor code reorganising

**Full Changelog**: https://github.com/pdphilip/laravel-elasticsearch/compare/v5.0.3...v5.0.4

## v5.0.3 - 2025-03-28

This release is compatible with Laravel 10, 11 & 12

### What's changed

- Bug fix: Internal model attribute `meta` renamed to `_meta` to avoid the issue where a model could have a field called `meta`
- Bug fix: `highlight()` passed without fields did not highlight all hits
- Bug fix:  Hybrid `BelongsTo` in some SQL cases used ES connection
- Bug fix: `orderBy('_score')` was not parsing correctly
- Bug fix: Edge case where a string value was being seen as callable

**Full Changelog**: https://github.com/pdphilip/laravel-elasticsearch/compare/v5.0.2...v5.0.3

## v5.0.2 - 2025-03-28

This release is compatible with Laravel 10, 11 & 12

### What's changed

#### 1. New feature, `bulkInsert()`

`bulkInsert()` is identical to `insert()` but will continue on errors and return an array of the results.

```php
People::bulkInsert([
    [
        'id' => '_edo3ZUBnJmuNNwymfhJ', // Will update (if id exists)
        'name' => 'Jane Doe',
        'status' => 1,
    ],
    [
        'name' => 'John Doe',  // Will Create
        'status' => 2,
    ],
    [
        'name' => 'John Dope',
        'status' => 3,
        'created_at' => 'xxxxxx', // Will fail
    ],
]);








```
Returns:

```json
{
  "hasErrors": true,
  "total": 3,
  "took": 0,
  "success": 2,
  "created": 1,
  "modified": 1,
  "failed": 1,
  "errors": [
    {
      "id": "Y-dp3ZUBnJmuNNwy7vkF",
      "type": "document_parsing_exception",
      "reason": "[1:45] failed to parse field [created_at] of type [date] in document with id 'Y-dp3ZUBnJmuNNwy7vkF'. Preview of field's value: 'xxxxxx'"
    }
  ]
}







```
#### 2. Bug fix: `distinct()` aggregation now appends `searchAfter` key in meta

**Full Changelog**: https://github.com/pdphilip/laravel-elasticsearch/compare/v5.0.1...v5.0.2

## v5.0.1 - 2025-03-28

This release is compatible with Laravel 10, 11 & 12

### What's changed

- Updated model docs for comprehensive IDE support when building queries
- Added `orderByNestedDesc()`
- Removed & replaced compatibility-loader that depended on `class_alias` to set the correct traits for the given Laravel version

**Full Changelog**: https://github.com/pdphilip/laravel-elasticsearch/compare/v5.0.0...v5.0.1

## v5.0.0 - 2025-03-26

We’re excited to announce v5 of the laravel-elasticsearch package - compatible with **Laravel 10, 11, and 12.**

### Acknowledgement

V5 is the brainchild of [@use-the-fork](https://github.com/use-the-fork) and is a near-complete rewrite of the package; packed with powerful new features, deep integration with Elasticsearch’s full capabilities, and a much tighter alignment
with Laravel’s Eloquent. It lays a solid, future-proof foundation for everything that comes next.

### Upgrading

- Please take a look at the [upgrade guide](https://elasticsearch.pdphilip.com/upgrade-guide/) carefully, as there are several significant breaking changes.
  
- [New features are detailed here](https://elasticsearch.pdphilip.com/whats-new/)
  

```json
"pdphilip/elasticsearch": "^5",







```
### Breaking Changes

#### 1. Connection

- **Index Prefix Handling**
  The `ES_INDEX_PREFIX` no longer auto-appends an underscore (`_`).
  Old behavior: `ES_INDEX_PREFIX=my_prefix` → `my_prefix_`
  New: set explicitly if needed → `ES_INDEX_PREFIX=my_prefix_`

#### 2. Models

- **Model ID Field**
  `$model->_id` is deprecated. Use `$model->id` instead.
  If your model had a separate `id` field, you must rename it.
  
- **Default Limit Constant**
  `MAX_SIZE` constant is removed. Use `$defaultLimit` property:
  
  ```php
  use PDPhilip\Elasticsearch\Eloquent\Model;
  
  class Product extends Model
  {
      protected $defaultLimit = 10000;
      protected $connection = 'elasticsearch';
  }
  
  
  
  
  
  
  
  ```

#### 3. Queries

- `where()` Behavior Changed
  
  Now uses term query instead of match.
  
  ```php
  // Old:
  Product::where('name', 'John')->get(); // match query
  // New:
  Product::whereMatch('name', 'John')->get(); // match query
  Product::where('name', 'John')->get();      // term query
  
  
  
  
  
  
  
  ```
- `orderByRandom()` Removed
  
  Replace with `functionScore()` [Docs](https://elasticsearch.pdphilip.com/upgrade-guide#queries)
  
- Full-text Search Options Updated
  Methods like `asFuzzy()`, `setMinShouldMatch()`, `setBoost()` removed.
  Use callback-based SearchOptions instead:
  
  ```php
  Product::searchTerm('espresso time', function (SearchOptions $options) {
        $options->searchFuzzy();
      $options->boost(2);
        $options->minimumShouldMatch(2);
  })->get();
  
  
  
  
  
  
  
  ```
- Legacy Search Methods Removed
  All `{xx}->search()` methods been removed. Use `{multi_match}->get()` instead.
  

#### 4. Distinct & GroupBy

- `distinct()` and `groupBy()` behavior updated. [Docs](https://elasticsearch.pdphilip.com/eloquent/distinct/)
  
  Review queries using them and refactor accordingly.
  

#### 5. Schema

- `IndexBlueprint` and `AnalyzerBlueprint` has been removed and replaced with a single `Blueprint` class
  
  ```diff
  -   use PDPhilip\Elasticsearch\Schema\IndexBlueprint;
  -   use PDPhilip\Elasticsearch\Schema\AnalyzerBlueprint;
  use PDPhilip\Elasticsearch\Schema\Blueprint;
  
  
  
  
  
  
  
  ```
- `Schema::hasIndex` has been removed. Use `Schema::hasTable` or `Schema::indexExists` instead.
  
- `geo($field)` field property has been replaced with `geoPoint($field)`
  
- `{field}->index($bool)` field property has been replaced with `{field}->indexField($bool)`;
  
- `alias()` field type has been removed. Use `aliasField()` instead.
  
- `settings()` method has been replaced with `withSetting()`
  
- `map()` method has been replaced with `withMapping()`
  
- `analyzer()` method has been replaced with `addAnalyzer()`
  
- `tokenizer()` method has been replaced with `addTokenizer()`
  
- `charFilter()` method has been replaced with `addCharFilter()`
  
- `filter()` method has been replaced with `addFilter()`
  

#### 6. Dynamic Indices

- Dynamic indices are now managed by the `DynamicIndex` trait. [upgrade guide](https://elasticsearch.pdphilip.com/upgrade-guide#dynamic-indices)

### New features

#### 1. Laravel-Generated IDs

- You can now generate Elasticsearch ids in Laravel [Docs](https://elasticsearch.pdphilip.com/eloquent/the-base-model#laravel-vs-elasticsearch-generated-ids)

#### 2. Fluent query options as a callback

- All clauses in the query builder now accept an optional callback of Elasticsearch options to be applied to the clause. [Docs](https://elasticsearch.pdphilip.com/eloquent/eloquent-queries#query-options)

#### 3. Belongs to Many Relationships

- Belongs to many relationships are now supported. [Docs](https://elasticsearch.pdphilip.com/relationships/es-es#many-to-many-belongstomany)

#### 4. New queries

- whereMatch() [Docs](https://elasticsearch.pdphilip.com/eloquent/es-queries#where-match)
- whereFuzzy() [Docs](https://elasticsearch.pdphilip.com/eloquent/es-queries#where-fuzzy)
- whereScript() [Docs](https://elasticsearch.pdphilip.com/eloquent/es-queries#where-script)

#### 5. New aggregations

- Boxplot Aggregations [Docs](https://elasticsearch.pdphilip.com/eloquent/aggregation#boxplot-aggregations)
- Stats Aggregations [Docs](https://elasticsearch.pdphilip.com/eloquent/aggregation#stats-aggregations)
- Extended Stats Aggregations - [Docs](https://elasticsearch.pdphilip.com/eloquent/aggregation#extended-stats-aggregations)
- Cardinality Aggregations - [Docs](https://elasticsearch.pdphilip.com/eloquent/aggregation#cardinality-aggregations)
- Median Absolute Deviation Aggregations - [Docs](https://elasticsearch.pdphilip.com/eloquent/aggregation#median-absolute-deviation-aggregations)
- Percentiles Aggregations - [Docs](https://elasticsearch.pdphilip.com/eloquent/aggregation#percentiles-aggregations)
- String Stats Aggregations - [Docs](https://elasticsearch.pdphilip.com/eloquent/aggregation#string-stats-aggregations)

#### 6. Migratons: Add Normalizer

- Normalizers can now be defined in migrations. [Docs](https://elasticsearch.pdphilip.com/schema/index-blueprint#addnormalizer)

#### 7. Direct Access to Elasticsearch PHP client

```php
Connection::on('elasticsearch')->elastic()->{clientMethod}();







```
### What's Changed

* V5.0.0 by @use-the-fork in https://github.com/pdphilip/laravel-elasticsearch/pull/54
* Small Bug Fixes found in RC1 by @use-the-fork in https://github.com/pdphilip/laravel-elasticsearch/pull/60

**Full Changelog**: https://github.com/pdphilip/laravel-elasticsearch/compare/v4.5.3...v5.0.0

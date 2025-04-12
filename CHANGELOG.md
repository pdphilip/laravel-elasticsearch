# Changelog

All notable changes to this `laravel-elasticsearch` package will be documented in this file.

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

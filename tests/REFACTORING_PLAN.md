# Test Refactoring Plan

## Completed: ID Strategy Consolidation

### What Changed

Instead of maintaining duplicate test files (`tests/` and `tests/WithIds/`), we now use a single set of models with a configurable ID strategy.

**Before:** 34 test files + 20 duplicate models
**After:** 19 test files + 20 models with `TestsWithIdStrategies` trait

### How It Works

All test models now include the `TestsWithIdStrategies` trait:

```php
class User extends Model
{
    use TestsWithIdStrategies;
    // ...
}
```

The trait checks the `ES_CLIENT_SIDE_IDS` environment variable:
- `false` (default): Elasticsearch generates document IDs
- `true`: Client generates UUIDs before insert

### Running Tests

```bash
# Run with ES-generated IDs (default)
./vendor/bin/pest

# Run with client-generated UUIDs
ES_CLIENT_SIDE_IDS=true ./vendor/bin/pest
```

### Files Deleted

- `tests/WithIds/` - All 15 duplicate test files
- `tests/Models/IdGenerated/` - All 20 duplicate models

### Files Added

- `tests/Concerns/TestsWithIdStrategies.php` - The configurable ID strategy trait

---

## Completed: QueryBuilderTest Conversion

### What Changed

Converted `QueryBuilderTest.php` from using `DB::table()` calls to using Model classes directly for better readability and consistency.

**Before:**
```php
DB::table('users')->insert(['name' => 'John Doe']);
$user = DB::table('users')->where('name', 'John Doe')->first();
expect($user['name'])->toBe('John Doe');
```

**After:**
```php
User::insert(['name' => 'John Doe']);
$user = User::where('name', 'John Doe')->first();
expect($user->name)->toBe('John Doe');
```

### Test Renamed

Tests were also renamed to be more descriptive:

| Old Name | New Name |
|----------|----------|
| `it tests delete with id` | `deletes document by id` |
| `it tests collection` | `returns query builder instance from model` |
| `it tests get` | `retrieves empty collection when no documents exist` |
| `it tests no document` | `returns empty results for non-matching queries` |
| `it tests insert` | `inserts document with array fields` |
| `it tests batch insert` | `batch inserts multiple documents` |
| `it tests find` | `finds document by id` |
| `it tests find null` | `returns null when finding null id` |
| `it tests count` | `counts documents in index` |
| `it tests update` | `updates document matching where clause` |
| `it tests delete` | `deletes documents matching where clause` |
| `it tests truncate` | `truncates all documents in index` |
| `it tests sub key` | `queries nested subdocument fields` |
| `it tests in array` | `queries values within array fields` |
| `it tests distinct` | `selects distinct values from field` |
| `it tests custom ID` | `handles custom document ids` |
| `it tests take` | `limits results with take` |
| `it tests skip and take` | `paginates with skip and take` |
| `it tests pluck` | `plucks single field values` |
| `it tests list` | `lists all values for field` |
| `it tests aggregate` | `computes min max sum avg count aggregations` |
| `it tests subdocument aggregate` | `aggregates subdocument numeric fields` |
| `it updates subdocument fields` | `updates nested subdocument fields` |
| `it handles dates correctly` | `handles date queries and comparisons` |
| `it uses pagination` | `computes pagination count with filters` |
| `it uses various query operators` | `supports various query operators` |
| `it uses various date query operators` | `queries by weekday month day and year` |
| `it pushes values to array fields in a document` | `pushes values to array fields` |
| `it pulls values from array fields in a document` | `pulls values from array fields` |
| `it increments and decrements user age` | `increments and decrements numeric fields` |
| `it verifies cursor returns lazy collection and checks names` | `returns lazy collection via cursor` |
| `it increments each specified field by respective values` | `increments multiple fields simultaneously` |
| `it validates increments each values` | `throws exception for non-numeric increment values` |
| `it validates increments each columns` | `throws exception for invalid increment column format` |

---

## Completed: New Test Coverage

### New Test Files Created

#### 1. `SearchMultiMatchTest.php`
Tests all multi-field search method variants:
- `searchTerm()` / `orSearchTerm()` / `searchNotTerm()` - best_fields
- `searchTermMost()` variants - most_fields
- `searchTermCross()` variants - cross_fields
- `searchPhrase()` variants - phrase matching
- `searchPhrasePrefix()` variants - phrase prefix
- `searchBoolPrefix()` variants - bool prefix for search-as-you-type
- `searchFuzzy()` / `searchFuzzyPrefix()` variants - fuzzy matching
- `searchQueryString()` variants - Elasticsearch query string syntax

#### 2. `PointInTimeTest.php`
Tests PIT API for large dataset pagination:
- `openPit()` / `closePit()`
- `viaPit()` / `searchAfter()` / `withPitId()` / `keepAlive()`
- `getPit()` - querying with PIT
- `chunkByPit()` - chunked iteration
- Cursor metadata methods

#### 3. `FilterWhereTest.php`
Tests filter context queries (no scoring impact):
- `filterWhere()` - basic filtering
- `filterWhereIn()` / `filterWhereNotIn()`
- `filterWhereBetween()`
- `filterWhereNull()` / `filterWhereNotNull()`
- `filterWhereDate()`
- `filterWhereTerm()` / `filterWhereMatch()` / `filterWherePhrase()`
- `filterWhereFuzzy()` / `filterWhereRegex()`
- `filterWhereGeoDistance()` / `filterWhereGeoBox()`
- `filterWhereNestedObject()`
- Post-filter for faceted search

#### 4. `AggregationAdvancedTest.php`
Tests advanced aggregations:
- `stats()` - combined statistics
- `extendedStats()` - variance, std deviation
- `boxplot()` - statistical distribution
- `cardinality()` - distinct count
- `percentiles()` - value distribution
- `medianAbsoluteDeviation()`
- `stringStats()` - text field statistics
- `matrix()` - multi-field correlation
- `agg()` - multi-metric aggregation
- `bucket()` / `groupBy()` / `groupByRanges()` / `groupByDateRanges()`
- `getAggregationResults()` / `getRawAggregationResults()`

#### 5. `DslOutputTest.php`
Tests query inspection and DSL output:
- `toDsl()` - get compiled query without execution
- `toCompiledQuery()` - alias method
- Query structure inspection (bool, filter, must_not, should)
- Sort, pagination, source selection structure
- Aggregation DSL output
- Debugging use cases

---

## Test File Summary

| File | Description | Test Count |
|------|-------------|------------|
| `AggregationAdvancedTest.php` | Advanced aggregations | 23 |
| `AggregationTest.php` | Basic aggregations | - |
| `AuthTest.php` | Authentication | - |
| `CacheTest.php` | Query caching | - |
| `ConnectionTest.php` | ES connection | - |
| `DslOutputTest.php` | Query DSL inspection | 21 |
| `EloquentTest.php` | Eloquent features | - |
| `FilterWhereTest.php` | Filter context queries | 19 |
| `GeoQueryTest.php` | Geo queries | - |
| `GroupByTest.php` | Group by operations | - |
| `HybridRelationTest.php` | Hybrid relations | - |
| `IndexTest.php` | Index operations | - |
| `ModelTest.php` | Model features | - |
| `PointInTimeTest.php` | PIT API | 14 |
| `PropertyTest.php` | Model properties | - |
| `QueryBuilderTest.php` | Query builder | 34 |
| `RelationsTest.php` | Model relations | - |
| `SchemaTest.php` | Schema operations | - |
| `SearchMultiMatchTest.php` | Multi-field search | 22 |
| `SearchTest.php` | Basic search | - |
| `SoftDeleteTest.php` | Soft deletes | - |

---

## Running the Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/SearchMultiMatchTest.php

# Run with client-generated UUIDs
ES_CLIENT_SIDE_IDS=true ./vendor/bin/pest

# Run with coverage
./vendor/bin/pest --coverage
```

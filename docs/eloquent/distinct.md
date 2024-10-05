# Distinct and GroupBy
In the Laravel-Elasticsearch integration, `distinct()` and `GroupBy()` methods play a pivotal role in data aggregation, particularly for cases where unique values or grouped data summaries are required. These methods leverage Elasticsearch's term aggregation capabilities, offering powerful data analysis tools directly within the Eloquent model.

For this Elasticsearch implementation, the `distinct()` and `groupBy()` methods are interchangeable and will yield the same results.

## Basic Usage
The `distinct()` and `groupBy()` methods are used to retrieve unique values of a given field.

### Distinct
```php
$users = UserLog::where('created_at', '>=', Carbon::now()->subDays(30))
    ->distinct()->get('user_id');

// Alternative syntax, explicitly selecting the field
$users = UserLog::where('created_at', '>=', Carbon::now()->subDays(30))
    ->select('user_id')->distinct()->get();
```
::: details Explain
Retrieves unique user_ids of users logged in the last 30 days
:::

### GroupBy
```php
$users = UserLog::where('created_at', '>=', Carbon::now()->subDays(30))
    ->groupBy('user_id')->get();
```
::: details Explain
Retrieves unique user_ids of users logged in the last 30 days
:::

## Working with Collections
The results from `distinct()` and `groupBy()` queries are returned as collections, enabling the use of Laravel's rich collection methods for further manipulation or processing.
```php
// Loading related user data from the distinct user_ids
$users = UserLog::where('created_at', '>=', Carbon::now()->subDays(30))
    ->distinct()->get('user_id');
return $users->load('user');
```
::: details Explain
Loads the related user data for the distinct user_ids
:::

## Multiple Fields Aggregation
The `distinct()` and `groupBy()` methods can be used to retrieve unique values of multiple fields.
```php
$users = UserLog::where('created_at', '>=', Carbon::now()->subDays(30))
    ->distinct()->get(['user_id', 'log_title']);
```
::: details Explain
Retrieves unique user_ids and log_titles of users logged in the last 30 days, example:
```json
[
  {
      "user_id": "1",
      "log_title": "LOGGED_IN"
  },
  {
      "user_id": "2",
      "log_title": "LOGGED_IN"
  },
  {
      "user_id": "2",
      "log_title": "LOGGED_OUT"
  }
]
```
:::

## Ordering by Aggregation Count
Sorting the results based on the count of aggregated fields or the distinct values themselves can provide ordered insights into the data.
```php
$users = UserLog::where('created_at', '>=', Carbon::now()->subDays(30))
    ->distinct()->orderBy('_count')->get('user_id');
```
::: details Explain
Retrieves unique user_ids of users logged in the last 30 days, ordered by the count of logs in ascending order
:::

::: tip The `_count` field is an internal field used by the package to reference the count of distinct values
:::
You can also order by the distinct values themselves:
```php
$users = UserLog::where('created_at', '>=', Carbon::now()->subDays(30))
    ->distinct()->orderBy('user_id')->get('user_id');
```
::: details Explain
Retrieves unique user_ids of users logged in the last 30 days, ordered by the user_id ascending
:::

## Returning Count values with Distinct Results
To include the count of distinct values alongside the results, use `distinct(true)`. This can be invaluable for analytics and reporting purposes.

::: tip This can only be achieved using the `distinct()` method
:::
```php
$users = UserLog::where('created_at', '>=', Carbon::now()->subDays(30))
    ->distinct(true)->orderByDesc('_count')->get('user_id');
```
::: details Explain
Retrieves unique user_ids of users logged in the last 30 days, with the count of logs ordered by the count in descending order. Example result:
```json
[
  {
    "user_id": "5",
    "user_id_count": 65
  },
  {
    "user_id": "1",
    "user_id_count": 61
  },
  {
    "user_id": "9",
    "user_id_count": 54
  }
]
```
:::

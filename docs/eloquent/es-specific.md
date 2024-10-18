# Elasticsearch Specific Queries
Elasticsearch offers a rich set of query capabilities, including geospatial queries and regex-based searches, which go beyond the traditional query types found in SQL databases. The Laravel-Elasticsearch integration provides a seamless interface to leverage these powerful features directly within your Laravel models.

## GeoBox
The `filterGeoBox` method allows you to retrieve documents based on geospatial data, specifically targeting documents where a geo-point field falls within a defined "box" on a map. This is particularly useful for applications requiring location-based filtering, such as finding all events within a specific geographical area.
```php
// Define the top-left and bottom-right coordinates of the box
$topLeft = [-10, 10]; // [latitude, longitude]
$bottomRight = [10, -10]; // [latitude, longitude]

// Retrieve UserLogs where 'agent.geo' falls within the defined box
$logs = UserLog::where('status', 7)->filterGeoBox('agent.geo', $topLeft, $bottomRight)->get();
```

## GeoPoint
The `filterGeoPoint` method filters results based on their proximity to a given point, specified by latitude and longitude, within a certain radius.
```php
// Specify the central point and radius
$point = [0, 0]; // [latitude, longitude]
$distance = '20km';

// Retrieve UserLogs where 'agent.geo' is within 20km of the specified point
$logs = UserLog::where('status', 7)->filterGeoPoint('agent.geo', $distance, $point)->get();
```
The `$distance` parameter is a string combining a numeric value and a distance unit (e.g., km for kilometers, mi for miles). Refer to the [Elasticsearch documentation](https://www.elastic.co/guide/en/elasticsearch/reference/current/api-conventions.html#distance-units) on distance units for more information.
::: tip The field must be of type geo for both filterGeoBox and filterGeoPoint methods otherwise your shards will fail, make sure to set the geo field in your migration, ex:
```php
Schema::create('user_logs',function (IndexBlueprint $index){
    $index->geo('agent.geo');
});
```
:::

## WhereExact
This method allows you to query for exact case-sensitive matches within a field. This is useful when you need to find a specific value. Keep in mind that the field will also need to have a keyword mapping.

Under the hood, this method uses the `term` query from Elasticsearch's Query DSL.

```php
return Person::whereExact('name', 'John Smith')->get();
```
::: details Explain
This will only return the documents where the name field is exactly 'John Smith'. 'john smith' or 'John' will not be returned.
:::

## WherePhrase
This method allows you to query for exact phrases within a field. This is useful when you need to search for a specific sequence of words within a text field.

Under the hood, this method uses the `match_phrase` query from Elasticsearch's Query DSL.

```php
return Person::wherePhrase('description', 'loves espressos')->get();
```
::: details Explain
This will only return the documents where the description field contains the exact phrase 'loves espressos'. Individual tokens like 'loves' or 'espressos' will not be returned in isolation.
:::

## WherePhrasePrefix
Similar to WherePhrase, this method allows you to query for exact phrases where the last word starts with a particular prefix.

Under the hood, this method uses the `match_phrase_prefix` query from Elasticsearch's Query DSL.

```php
return Person::wherePhrasePrefix('description', 'loves es')->get();
```
::: details Explain
This will only return the documents where the description field contains the the phrase 'loves es....'. Ex: 'loves espresso', 'loves essays' and 'loves eskimos' etc.
:::

## WhereTimestamp
This method allows you to query for timestamps on a known field and will **sanitize the input to ensure it is a valid timestamp for both seconds and milliseconds.**

```php
return Product::whereTimestamp('last_viewed', '<=', 1713911889521)->get();
```
::: details Explain
This will only return the documents where the last_viewed field is less than or equal to the timestamp 1713911889521 ms.
:::

## WhereRegex
The `WhereRegex` method allows you to query for documents based on a regular expression pattern within a field.

```php
$regex1 = Product::whereRegex('color', 'bl(ue)?(ack)?')->get();
$regex2 = Product::whereRegex('color', 'bl...*')->get();
```
::: details Explain
The first example will return documents where the color field matches the pattern 'bl(ue)?(ack)?', which means it can be 'blue' or 'black'. The second example will return documents where the color field matches the pattern 'bl...*', which means it starts with 'bl' and has at least three more characters. Both should return Blue or Black from the colors field.
:::

## RAW DSL Queries
For scenarios where you need the utmost flexibility and control over your Elasticsearch queries, the Laravel-Elasticsearch integration provides the capability to directly use Elasticsearch's Query DSL (Domain Specific Language). The results will still be returned as collections of Eloquent models.

```php
$bodyParams = [
  'query' => [
    'match' => [
      'color' => 'silver',
    ],
  ],
];

return Product::rawSearch($bodyParams); //Will search within the products index

return Product::rawSearch($bodyParams,true); //Will return the raw response from Elasticsearch
```
::: details Explain
The DSL example above uses the `match` query to search for products with the color 'silver'
:::

## RAW Aggregation Queries
Similar to raw search queries, you can also execute raw aggregation queries using Elasticsearch's Aggregation DSL. This allows you to perform complex aggregations on your data and retrieve the results in a structured format.

```php
$body = [
    'aggs' => [
        'price_ranges' => [
            'range' => [
                'field'  => 'price',
                'ranges' => [
                    ['to' => 100],
                    ['from' => 100, 'to' => 500],
                    ['from' => 500, 'to' => 1000],
                    ['from' => 1000],
                ],

            ],
            'aggs'  => [
                'sales_over_time' => [
                    'date_histogram' => [
                        'field'          => 'datetime',
                        'fixed_interval' => '1d',
                    ],

                ],
            ],
        ],
    ],
];
return Product::rawAggregation($body);
```
::: details Explain
The aggregation example above uses the `range` aggregation to group products into price ranges and the `date_histogram` aggregation to group sales over time within each price range.
:::

## To DSL
This method returns the parsed DSL query from the query builder. This can be useful when you need to inspect the raw query being generated by the query builder.
```php
$query = Product::where('price', '>', 100)->toDSL();
```
::: details Explain
This will return the raw DSL query generated by the query builder instance.
:::
::: tip `toDsl()` and the inherited `toSQL()` are the same method. You can use them interchangeably.
:::

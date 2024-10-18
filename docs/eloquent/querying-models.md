# Querying Models

Understanding how to query your models in Elasticsearch is crucial to leveraging the full potential of this package. This section covers the essentials of model querying, allowing you to fetch and interact with your data efficiently.

## All
Retrieve all records for a given model:

```php
$products = Product::all();
```

Equivalent to `get()` without clauses.

```php
$products = Product::get();
```

## Find
As with Eloquent, you can use the `find` method to retrieve a model by its primary key (_id). The method will return a single model instance or null if no matching model is found.

```php
$product = Product::find('IiLKG38BCOXW3U9a4zcn');
```
::: details Explain
Find the product with _id of `IiLKG38BCOXW3U9a4zcn` and return the model collection, or null if it does not exist.
:::

```php
$product = Product::findOrFail('IiLKG38BCOXW3U9a4zcn');
```
::: details Explain
Find the product with _id of `IiLKG38BCOXW3U9a4zcn` and return the model collection, or throw a `ModelNotFoundException` if no result is found.
:::

## First
As with Eloquent, you can use the `first` method to retrieve the first model that matches the query. The method will return a single model instance or null if no matching model is found.

```php
$product = Product::where('status',1)->first();
```
::: details Explain
Find the first product with a status of 1 and return the model collection, or null if it does not exist.
:::
```php
$product = Product::where('status',1)->firstOrFail();
```
::: details Explain
Find the first product with a status of 1 and return the model collection, or throw a `ModelNotFoundException` if no result is found.
:::

## Get
As with Eloquent, you can use the `get` method to retrieve all models that match the query. The method will return a model collection or an empty collection if no matching models are found.

```php
$product = Product::where('status',1)->get();
```
::: details Explain
Find all products with a status of 1 and return the model collection, or an empty collection if it does not exist.
:::

## Where
As with Eloquent, the `where` method is used to filter the query results based on the given conditions. The method accepts three parameters: the field name, an operator, and a value. The operator is optional and defaults to `=` if not provided.

```php
$products = Product::where('status',1)->take(10)->get();
```
::: details Explain
Find the first 10 products with a status of 1
:::

```php
$products = Product::where('manufacturer.country', 'England')->take(10)->get();
```
::: details Explain
Nested objects: Find the first 10 products with a manufacturer country of England
```json
{
    "status": 1,
    "manufacturer": {
        "country": "England"
    }
}
```
:::

```php
$products = Product::where('status','>=', 3)->take(10)->get();
```
::: details Explain
Find the first 10 products with a status greater than or equal to 3
:::

```php
$products = Product::where('color','!=', 'red')->take(10)->get(); //*See notes
```
::: details Explain
Find the first 10 products with a color that is not red
:::

::: tip
This query will also include collections where the color field does not exist, to exclude these, use `whereNotNull()`
:::

### See ES-specific queries for more complex queries like
- WhereExact
- WherePhrase
- WherePhrasePrefix
- WhereTimestamp
- WhereRegex

## Where using LIKE
Using the `like` operator in your where clause works differently here than in SQL. Since Elasticsearch will match tokens, you can use a normal `where` clause to search for partial matches (assuming text field with the standard analyser).

For this package, you can use the `like` operator to search for partial matches within tokens. The package will automatically convert the `like` operator to a wildcard query, and will search for the term in the field. For example, to search for products with a color that contains the letters `bl` (blue, black, etc.), you can use the following query:

```php
$products = Product::where('color', 'like', 'bl')->orderBy('color.keyword')->get();
```
::: details Explain
Find all products with a color that contains the letters bl and order the results alphabetically by the color field
:::

## WhereNot
The `whereNot` method is used to exclude documents that match the condition.

```php
$products = Product::whereNot('status', 1)->get();
```
::: details Explain
Find all products that do not have a status of 1, identical to `where('status', '!=', 1)`
:::

## AND statements
The `where` method can be chained to add more conditions to the query. This will be read as `AND` in the query.

```php
$products = Product::where('is_active', true)->where('in_stock', '<=', 50)->get();
```
::: details Explain
Find all products that are active and have 50 or less in stock
:::

## OR Statements
The `orWhere` method can be used to add an `OR` condition to the query.

```php
$surplusProducts = Product::where('is_active', false)->orWhere('in_stock', '>=', 100)->get();
```
::: details Explain
Find all products that are not active or have 100 or more in stock
:::

## Chaining OR/AND statements
You can chain `where` and `orWhere` methods to create complex queries.

```php
$products = Product::where('type', 'coffee')
                ->where('is_approved', true)
                ->orWhere('type', 'tea')
                ->where('is_approved', false)
                ->get();
```
::: details Explain
Find all products that are either coffee and approved, or tea and not approved. The query reads as: Where type is coffee and is approved, or where type is tea and is not approved.
:::

::: tip Order of chaining matters , It reads naturally from left to right having

- where() as AND where
- orWhere() as OR where

In the above example, the query would be:
`(type:"coffee" AND is_approved:true) OR (type:"tea" AND is_approved:false)`
:::

## WhereIn
The `whereIn` method is used to include documents that match any of the values passed in the array.

```php
$products = Product::whereIn('status', [1,5,11])->get();
```
::: details Explain
Find all products with a status of 1, 5, or 11
:::

::: tip The `whereIn` method is effectivly a concise OR statement on the same field. The above query is equivalent to:

```php
$products = Product::where('status', 1)->orWhere('status', 5)->orWhere('status', 11)->get();
```
:::

## WhereNotIn
The `whereNotIn` method is used to exclude documents that match any of the values passed in the array.

```php
$products = Product::whereNotIn('color', ['red','green'])->get();
```
::: details Explain
Find all products that do not have a color of red or green
:::

::: tip The `whereNotIn` method is effectively a concise AND NOT statement on the same field. The above query is equivalent to:

```php
$products = Product::where('color', '!=', 'red')->where('color', '!=', 'green')->get();
```
:::

## WhereNull
Can be read as Where `field` does not exist

In traditional SQL databases, whereNull is commonly used to query records where a specific column's value is NULL, indicating the absence of a value. However, in Elasticsearch, the concept of NULL applies to the absence of a field as well as the field having a value of NULL.

**Therefore, in the context of the Elasticsearch implementation within Laravel, `whereNull` and `WhereNotNull` have been adapted to fit the common Elasticsearch requirement to query the existence or non-existence of a field as well as the null value of the field.**

```php
$products = Product::whereNull('color')->get();
```
::: details Explain
Find all products that do not have a color field
:::

## WhereNotNull
Can be read as Where `field` Exists.

::: tip Using `whereNotNull` is more common than it's counterpart (`whereNull`) since any negation style query of a field will include documents that do not have the field. Thus to negate values and only include documents that have the field, `whereNotNull` is used.
:::

```php
$products = Product::whereNotIn('color', ['red','green'])->whereNotNull('color')->get();
```
::: details Explain
Find all products that do not have a color of red or green, and ensure that the color field exists
:::

## WhereBetween
As with Eloquent, the `whereBetween` method is used to filter the query results based on the given range. The method accepts two parameters: the field name and an array containing the minimum and maximum values of the range.

```php
$products = Product::whereBetween('in_stock', [10, 100])->get();
```
::: details Explain
Find all products with an in_stock value between 10 and 100 (including 10 and 100)
:::

```php
$products = Product::whereBetween('orders', [1, 20])->orWhereBetween('orders', [100, 200])->get();
```
::: details Explain
Find all products with an orders value between 1 and 20, or between 100 and 200 (including 1, 20, 100, and 200)
:::

## Grouped Queries
As with native Laravel Eloquent, `where` (and alike) clauses can accept a `$query` closure to group multiple queries together.

```php
$products = Product::where(function ($query) {
    $query->where('status', 1)
          ->orWhere('status', 2);
})->get();
```

A more advanced example:
```php
$products = Product::whereNot(function ($query) {
    $query->where('color', 'lime')->orWhere('color', 'blue');
})->orWhereNot(function ($query) {
    $query->where('status', 2)->where('is_active', false);
})->orderBy('status')->get();``
```
::: details Explain
Find all products with an in_stock value between 10 and 100 (including 10 and 100)
:::

```php
$products = Product::whereBetween('orders', [1, 20])->orWhereBetween('orders', [100, 200])->get();
```
::: details Explain
Find all products that do not have a color of lime or blue, or do not have a status of 2 and are not active, and order the results by status
:::

## Dates
Elasticsearch by default converts a date into a timestamp, and applies the `strict_date_optional_time||epoch_millis` format. If you have not changed the default format for your index then acceptable values are:
- 2022-01-29
- 2022-01-29T13:05:59
- 2022-01-29T13:05:59+0:00
- 2022-01-29T12:10:30Z
- 1643500799 (timestamp)

With Carbon
```php
Carbon::now()->modify('-1 week')->toIso8601String()
```
You can use these values in a normal [where](#where) clause, or use the built-in date clause, ie:
### WhereDate()
```php
$products = Product::whereDate('created_at', '2022-01-29')->get();
```
::: tip The usage for `whereMonth` / `whereDay` / `whereYear` / `whereTime` has been disabled for the current version of this plugin
:::

::: warning IMPORTANT
Note on saving fields with an empty string vs NULL
:::

## Empty strings values
**Avoid saving fields with empty strings**, as Elasticsearch will treat them as a value and not as a `null` field. Rather, use null or simply do not include the field when writing the document.

### Good example:
```php
$product = new Product();
$product->name = 'Glass bowl';
$product->color = null;
$product->save();
```
or

```php
$product = new Product();
$product->name = 'Glass bowl';
$product->save();
```
### Bad example:
```php
$product = new Product();
$product->name = 'Glass bowl';
$product->color = '';
$product->save();
```
If you need to find products without a color, the above product will not be included in the results. Further, querying for products with an empty string requires special handling since `where('color', '')` will not work as expected.

::: tip If you need to query for an empty string, you can use the following:
```php
$products = Product::whereIn('color', [''])->get();
```
&nbsp;
```php
$products = Product::whereExact('color', '')->get(); //specific to this package
```
:::

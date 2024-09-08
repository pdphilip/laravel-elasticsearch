# Ordering and Pagination
In the Laravel-Elasticsearch integration, ordering and pagination are essential features that enable developers to manage and present data effectively. These features are designed to work seamlessly within the Laravel ecosystem, providing a familiar experience to those accustomed to Eloquent ORM.

## Ordering
Elasticsearch inherently ranks search results based on relevance (using internal scoring and ranking algorithms). However, it is often necessary to sort results based on specific fields. The Laravel-Elasticsearch integration provides a simple and intuitive way to sort search results using the `orderBy` and `orderByDesc` methods.

::: tip You can only sort on **keyword** and **numeric** fields. Sorting on **text** fields is not supported by Elasticsearch and will throw an error (No shards available)
:::

## OrderBy
The `orderBy` method allows you to specify the field by which the results should be sorted and the direction of the sort (ascending or descending). This method is straightforward and aligns with the Laravel Eloquent's `orderBy` functionality.
```php
$products = Product::orderBy('status')->get();
```
::: details Explain
Find all products and order them by their status in ascending order.
:::
```php
$products = Product::orderBy('created_at', 'desc')->get();
```
::: details Explain
Find all products and order them by creation date in descending order.
:::
If you have a field that is mapped as a `text` field with a `keyword` subfield, you will need to sort on the keyword subfield:
```php
$products = Product::orderBy('name.keyword')->get();
```
::: details Explain
Find all products and order them by their name in ascending order via the keyword subfield.
:::

### OrderByDesc
As with Laravel's standard eloquent, the `orderByDesc` method is provided to quickly sort results in descending order by a specified field, without needing to explicitly set the direction.
```php
$products = Product::orderByDesc('created_at')->get();
```
::: details Explain
Find all products and order them by creation date in descending order.
:::

## Offset & Limit (skip & take)
As with Eloquent, you can use the `skip` and `take` methods in your query.
```php
$products = Product::skip(10)->take(5)->get();
```
::: details Explain
Find all products and skip the first 10, then take the next 5.
:::

### Pagination
Pagination works as expected in this Laravel-Elasticsearch integration.
```php
$products = Product::where('is_active',true)
$products = $products->paginate(50)
```
::: details Explain
Find all active products and paginate them with 50 products per page.
:::

Pagination links (Blade)
```php
{{ $products->appends(request()->query())->links() }}
```
## Extending ordering for Elasticsearch features
The `orderBy` and `orderByDesc` methods are designed to be Laravel native, but they can be extended to support more advanced Elasticsearch features.

Full parameter scope:
* `orderBy($field, $direction = 'asc', $mode = 'min', $missing = '_last')`
* `orderByDesc($field, $mode = 'min', $missing = '_last')`

#### $mode
The `mode` parameter allows you to specify how Elasticsearch should handle sorting when multiple documents have the same value for the field being sorted. Options: `min`, `max`, `sum`, `avg`, `median`

Default: `min` when sorting in ascending order, `max` when sorting in descending order.

#### $missing
Default: `_last`

The `missing` parameter allows you to specify how Elasticsearch should handle sorting when a document is missing the field being sorted. Options: `_first`, `_last`, or a custom value.

Example with `mode`:
```php
// pricing_history is an array of prices ex: [9.99, 15.50, 29, 20.50]
$products = Product::where('is_active',true)->orderBy('pricing_history', 'desc', 'avg')->get();
```
::: details Explain
Find all active products and order them by the average price in the pricing history array in descending order.
:::

Example with `missing`:
```php
$products = Product::where('is_active',true)->orderBy('color.keyword', 'desc', null, '_first')->get();
```
::: details Explain
Find all active products and order them by color in descending order. If a product does not have a color, it will be placed at the beginning of the list.

`$mode = null` will use the default mode for the sort direction. In this case, `max` for descending order.
:::

## OrderBy Geo Distance
The `orderByGeo` method is a specialized method for sorting by geo distance. This sorting method only works with `geo` fields and can be used to sort results based on their distance from a specified point.

Parameter scope:

* `orderByGeo(string $column, array $pin, $direction = 'asc', $unit = 'km', $mode = null, $type = 'arc')`
* `orderByGeoDesc(string $column, array $pin, $unit = 'km', $mode = null, $type = 'arc')`

### `$pin`
The point(s) from which to calculate the distance. This can be a single point or an array of points.

* A point is an array with two values: `[lon, lat]` **(longitude, latitude) - Order matters** and Elasticsearch does not use the standard `[lat, lon]` format
* For multiple points, use an array of points: `[[lon1, lat1], [lon2, lat2], ...]`
* You can also specify the lat and lon keys: `['lat' => 51.50853, 'lon' => -0.12574]`

### `$direction`
* The direction in which to sort the results. Options: `asc`, `desc`
* `asc` sorts by shortest distance from the pin
* `desc` sorts by longest distance from the pin

### `$unit`
The unit to use when computing sort values. Options: `m`, `km`, `mi`, `yd`, `ft`

### `$mode`
Used for when a field has several geo points. By default, the **shortest distance is used when sorting in ascending order** and **the longest distance when sorting in descending order**.
Options: `min`, `max`, `sum`, `avg`, `median`

### `$type`
* The type of distance calculation to use.
* Options: `arc`, `plane`
* Note: `plane` is faster, but inaccurate on long distances and close to the poles.
```php
// Lat:51.50853 & Lon:-0.12574 (London)
return Product::where('is_active',true)->orderByGeo('manufacturer.location', [-0.12574, 51.50853])->get();
// OR
return Product::where('is_active',true)->orderByGeo('manufacturer.location', ['lat' => 51.50853, 'lon' => -0.12574])->get();
```
::: details Explain
Find all active products and order them by the closest distance of the manufacturer's location to London.
:::
```php
// Lat:48.85341 & Lon:2.3488 (Paris)
// Lat:51.50853 & Lon:-0.12574 (London)
return Product::where('is_active',true)->orderByGeo('manufacturer.location', [[2.3488, 48.85341], [-0.12574, 51.50853]], 'desc', 'km', 'avg', 'plane')->get();
//Or
return Product::where('is_active',true)->orderByGeo('manufacturer.location', [['lat' => 48.85341, 'lon' => 2.3488], ['lat' => 51.50853, 'lon' => -0.12574]], 'desc', 'km', 'avg', 'plane')->get();
```
::: details Explain
Find all active products and order them by the longest average distance of the manufacturer's location to Paris and London.
:::

## Other sorting options include:
* orderByNested

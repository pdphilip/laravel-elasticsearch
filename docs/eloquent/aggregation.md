# Aggregation
Aggregations are powerful tools in Elasticsearch that allow you to summarize, compute statistics, and analyze data trends within your dataset. In the Laravel-Elasticsearch integration, aggregations are simplified to align with Eloquent's method of handling aggregate functions, making it intuitive for developers to perform complex data analyses.

## Basic Aggregations
You can use standard aggregate functions such as `count()`, `max()`, `min()`, `avg()`, and `sum()` directly on your Eloquent models, just like you would with a SQL database. These functions provide quick insights into your dataset.
```php
$totalSales = Sale::count(); // Total number of sales
$highestPrice = Sale::max('price'); // Maximum sale price
$lowestPrice = Sale::min('price'); // Minimum sale price
$averagePricePerSale = Sale::avg('price'); // Average sale price
$totalEarnings = Sale::sum('price'); // Sum of all sale prices

//Multiple fields at once
$highestPriceAndDiscountValue = Sale::max(['price','discount_amount']);
```
These aggregation functions are straightforward and mirror the typical usage in Laravel's Eloquent ORM, providing a seamless experience for developers.

Aggregations with Conditions

As the aggregation functions are part of the Eloquent ORM, you can also use them with conditions to filter the data you want to analyze.
```php
$averagePrice = Product::whereNotIn('color', ['red', 'green'])->avg('price');
```
::: details Explain
Average price of products excluding 'red' and 'green' colored products
:::

## Grouped Aggregations
`agg()` is an optimization method that allows you to call multiple aggregation functions on a single field in one call.

This call saves you from making multiple queries to get different statistics for the same field.
```php
$stats = Product::where('is_active',true)->agg(['count','avg','min','max','sum'],'sales');
```
::: details Explain
Returns count, average, minimum, maximum, and sum of sales for active products
:::

Available aggregation functions: `count`, `avg`, `min`, `max`, `sum`, `matrix`.

## Elasticsearch Matrix Aggregations
Elasticsearch offers advanced aggregation capabilities, including matrix stats aggregations, which provide comprehensive statistics about multiple fields. The Laravel-Elasticsearch integration simplifies the usage of these advanced features.
```php
// Matrix stats for 'price' field, excluding 'red' and 'green' colored products
$stats = Product::whereNotIn('color', ['red', 'green'])->matrix('price');

// Matrix stats for both 'price' and 'orders' fields
$stats = Product::whereNotIn('color', ['red', 'green'])->matrix(['price', 'orders']);
```
Example result for `matrix(['price', 'orders']);`:
```json
{
  "matrix": {
    "doc_count": 24,
    "fields": [
      {
        "name": "price",
        "count": 24,
        "mean": 944.1987476348877,
        "variance": 392916.60523541126,
        "skewness": 0.1301389603055256,
        "kurtosis": 1.6419181162499876,
        "covariance": {
          "price": 392916.60523541126,
          "orders": 5569.635773119718
        },
        "correlation": {
          "price": 1,
          "orders": 0.03421141805225852
        }
      },
      {
        "name": "orders",
        "count": 24,
        "mean": 501.79166666666663,
        "variance": 67454.5199275362,
        "skewness": 0.31085136523548346,
        "kurtosis": 1.9897405370026835,
        "covariance": {
          "price": 5569.635773119718,
          "orders": 67454.5199275362
        },
        "correlation": {
          "price": 0.03421141805225852,
          "orders": 1
        }
      }
    ]
  }
}
```
## Matrix Results Explained
The result of a matrix aggregation is a detailed statistical summary of the selected fields. Here's a breakdown of what each statistic represents:
* **doc_count**: The total number of documents that matched the aggregation query.

* **fields**: An array containing the statistical data for each field included in the matrix aggregation.
 * **name**: The name of the field.
 * **count**: The number of values analyzed for this field.
 * **mean**: The average value.
 * **variance**: The variance indicating the data's spread.
 * **skewness**: A measure of the asymmetry of the data distribution.
 * **kurtosis**: A measure of the 'tailedness' of the data distribution.
 * **covariance**: The covariance between the current field and other fields in the matrix, indicating how the fields vary together.
 * **correlation**: The correlation between the current field and other fields, showing the strength and direction of a linear relationship.


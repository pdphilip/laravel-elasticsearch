# Deleting Models
The Laravel-Elasticsearch integration facilitates model deletion in a manner that's highly consistent with the Laravel Eloquent ORM, offering a familiar interface for developers

## Single Model Deletion
To delete a single model, first, retrieve the model instance using the find method and then call the delete method on the instance. This operation removes the document from the Elasticsearch index corresponding to the model.
```php
$product = Product::find('IiLKG38BCOXW3U9a4zcn');
$product->delete();
```
::: details Explain
Find the product with the ID `IiLKG38BCOXW3U9a4zcn` and delete it.
:::

## Mass Deletion
For deleting multiple models based on certain criteria, you can chain the delete method to a query.
```php
Product::whereNull('color')->delete();
```
::: details Explain
Delete all products where the color is null or the color field is missing.
:::

## Truncating an Index
The truncate method removes all documents from an index without deleting the index itself. This is useful for quickly clearing all data while preserving the index settings and mappings.
```php
Product::truncate();
```
::: details Explain
Remove all documents from the products index but keep the index itself.
:::

## Destroy by `_id`
Single `_id`
```php
Product::destroy('9iKKHH8BCOXW3U9ag1_4');
```
Multiple `_id`s
```php
Product::destroy('4yKKHH8BCOXW3U9ag1-8', '_iKKHH8BCOXW3U9ahF8Q');
```
Multiple `_id`s as an array
```php
Product::destroy(['4yKKHH8BCOXW3U9ag1-8', '_iKKHH8BCOXW3U9ahF8Q']);
```

## Soft Deletes
Soft deletion is implemented to allow "deleting" a model without actually removing it from the Elasticsearch index. Instead, a `deleted_at` timestamp is added to the document, and the document is excluded from queries by default.

To use soft deletes, include the `SoftDeletes` trait in your model:
```php
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Eloquent\SoftDeletes; // [!code highlight]

class Product extends Model
{
    use SoftDeletes; // [!code highlight]
}
```
With soft deletes enabled, you can **include deleted models** in your query results using the `withTrashed()` method:
```php
Product::withTrashed()->where('color', 'red')->get();
```
::: details Explain
Retrieve all products with the color red, including any soft-deleted products.
:::

With soft deletes enabled, you can **restore** soft-deleted collections using the `restore()` query:
```php
Product::withTrashed()->where('color', 'red')->restore();
```
::: details Explain
Find all products with the color red and restore any that may have been soft-deleted.
:::

To permanently remove a soft-deleted collection, you can use the **forceDelete** method:
```php
Product::withTrashed()->where('discontinued_at', '<', '2020-01-01')->forceDelete();
```

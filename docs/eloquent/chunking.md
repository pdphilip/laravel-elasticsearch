# Chunking
In scenarios where you're dealing with large datasets, Laravel's chunk method becomes invaluable, allowing you to process large sets of results in manageable portions. This approach is particularly useful in memory-intensive operations, such as batch updates or exports, where loading the entire dataset into memory at once could lead to performance issues. The Laravel-Elasticsearch integration adapts this familiar concept from Laravel Eloquent ORM to work within the context of Elasticsearch data.

## Basic Chunking
The chunk method breaks down the query results into smaller "chunks" of a specified size, executing a callback function for each chunk. This allows you to operate on a subset of the results at a time, reducing memory usage.
```php
Product::chunk(1000, function ($products) {
    foreach ($products as $product) {
        // Example operation: Increase price by 10%
        $product->price *= 1.1;
        $product->save();
    }
});
```
::: details Explain
Retrieves products in batches of 1000. For each batch, it iterates over each product, applying a 10% price increase and then saving the changes.
:::

## Chunking, Under the Hood - PIT
* The chunking method uses Elasticsearch's `Point In Time` (PIT) to iterate over the results. This allows for more efficient pagination and avoids potential issues that may arise when records change during the operation.
* The default ordering will be _`shard_doc` in ascending order. You can introduce your own ordering by using the `orderBy` method before calling `chunk` however, this may affect the grouping of results.
* Once the chunking operation is complete, the PIT will be automatically closed.
* The default `keepAlive` duration is `5 minutes` but will be closed automatically if the operation completes before the duration elapses.

If 5 minutes is not enough for your operation, you can increase the `keepAlive` duration by setting the `keepAlive` parameter after your callback in the `chunk` method. Example:
```php
Product::chunk(1000, function ($products) {
    foreach ($products as $product) {
        // Example operation: Increase price by 10%
        $product->price *= 1.1;
        $product->save();
    }
},'20m');
```
::: details Explain
Retrieves products in batches of 1000. For each batch, it iterates over each product, applying a 10% price increase and then saving the changes. Keeps the PIT alive for 20 minutes.
:::

`keepAlive` parameter uses Elasticsearch's time units, such as `1m` for 1 minute, `1h` for 1 hour, and `1d` for 1 day.

## Chunk By Id
`chunkById($count, callable $callback, $column = '_id', $alias = null, $keepAlive = '5m')`

The `chunkById()` method is a specialized version of the `chunk` method that paginates results based on a unique identifier **ensuring that each chunk contains unique records**.

Any ordering clauses will be ignored as this method uses the unique identifier to paginate the results.

If no identifier is provided (default value `_id`), the chunk will use PIT ordered by `_shard_doc` in ascending order (irrespective of an order clause if present).
```php
Product::chunkById(1000, function ($products) {
    foreach ($products as $product) {
        // Example operation: Increase price by 10%
        $product->price *= 1.1;
        $product->save();
    }
}, 'product_sku.keyword');
```
::: details Explain
Retrieves products in batches of 1000, using the `product_sku` field as the unique identifier. For each batch, it iterates over each product, applying a 10% price increase and then saving the changes.
:::

# Saving Models

Saving models in the Laravel-Elasticsearch integration follows the conventional Laravel Eloquent patterns, making it easy for developers to transition or work with Elasticsearch alongside relational databases.

## Save a new model
### Option A: Attribute Assigning
You can create a new model instance, set its attributes individually, and then save it to the Elasticsearch index. This approach is straightforward and mirrors the typical Laravel ORM usage.

```php
$log = new UserLog;
$log->user_id = $userId;
$log->title = $title;
$log->status = 1;
$log->save();
```
::: details Explain
Create a new model instance, set its attributes individually, and then save it to the Elasticsearch index.
:::

### Option B: Mass Assignment via `create()`
The `create()` method allows for mass assignment of model attributes using an associative array. This is a concise and efficient way to create and save a new model instance in one step.

```php
$log = UserLog::create([
    'user_id' => $userId,
    'title' => $title,
    'status' => 1,
]);
```
::: tip Keep in mind, the `$fillable` and `$guarded` attributes are honored when using `create()` or `update()`
:::

## Updating a model
Updating models in Elasticsearch is consistent with Eloquent's approach, where you fetch a model, change the attributes, and then call `save()`.

```php
$log = UserLog::where('status', 1)->first();
$log->status = 2;
$log->save();
```
::: details Explain
Fetch the first User Log with status 1, change the status to 2, and then save the model.
:::

For mass updates, you can use the `update()` method on a query builder instance, which allows updating multiple documents matching the query criteria in one operation.

```php
$updates = Product::where('status', 1)->update(['status' => 4]);
// $updates will hold the number of documents updated
```

## Fast Saves
::: warning IMPORTANT: Saving without refresh
:::
Elasticsearch operates with a near real-time index, which means there's a slight delay between indexing a document and when it becomes searchable. By default, this package saves a document and waits for the index to refresh, ensuring the document is immediately available and up to date. However, this can introduce latency in write-heavy applications.

To optimize performance, you can use `saveWithoutRefresh()` or `createWithoutRefresh()`, which skips the wait for index refresh. **This is beneficial when immediate document retrieval is not necessary.**
```php
$log->saveWithoutRefresh();
```
and
```php
UserLog::createWithoutRefresh($attributes);
```
::: danger **Caution**: Using `saveWithoutRefresh` and updating the model immediately after can lead to unexpected outcomes, such as duplicate documents.
:::
```php
// This pattern should be avoided:
$log = new UserLog;
$log->user_id = $userId;
$log->title = $title;
$log->status = 1;
$log->saveWithoutRefresh(); // [!code highlight]
$log->company_id = 'ABC-123';
$log->saveWithoutRefresh(); // May result in two separate documents // [!code highlight]
```
## First Or Create
The `firstOrCreate()` method retrieves the first model matching the given attributes or creates a new model if no match is found. It takes two arguments:

- `$attributes`: An associative array of attributes to **search for** or create with.
- `$values`: An associative array of values to set on the model if it is created.

### Use with caution
- This method will take the `$attributes` array and make a "best guess" as to how to build a query from that. String values will be treated as exact matches (Required to be a `keyword`) and everything else a normal `where` clause.
- Don't overload the `$attributes` array with too many values, use it for **searching unique values**, then fill the `$values` array with the rest of the values.
```php
$book = Book::firstOrCreate(
    ['title' => $title,'author' => $author], //$attributes
    ['description' => $description, 'stock' => 0] //values
 );
```
::: details Explain
- Search for a book with the given title and author.
- If found, return the collection.
- If not found, create a new book with the given title, author, description, and stock = 0, and return it.
:::
## First Or Create Without Refresh
Added to the family of saving without refresh methods, `firstOrCreateWithoutRefresh()` is a new method that's identical to the `firstOrCreate()` method but without waiting for the index to refresh.
```php
$book = Book::firstOrCreateWithoutRefresh(
    ['title' => $title,'author' => $author], //$attributes
    ['description' => $description, 'stock' => 0] //values
 );
```
::: details Explain
- Search for a book with the given title and author.
- If found, return the collection.
- If not found, create a new book with the given title, author, description, and stock = 0, **without waiting for Elasticsearch** to index and return it.
  :::

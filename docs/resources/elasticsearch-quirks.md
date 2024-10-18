# Elasticsearch Quirks

Understanding Elasticsearch's unique behaviors can significantly improve your interaction with it, especially when using it through this Laravel plugin. Here are some common scenarios you might encounter:

## Error: all shards failed

This error often points to an **index mapping issue**, such as:

- Attempting to sort by a field that is indexed as text without a keyword sub-field.
- Using a filter operation on a field that is not explicitly mapped to that filterable type.

**Solutions:**

- Ensure you are using the correct field type for your operations. For sorting, use keyword types or text fields with a keyword sub-field.
- Review your index mappings to ensure they align with your query requirements.

## Default Search Limit

Elasticsearch defaults to returning 10 results for search queries. This plugin extends that default to 1000 for more expansive data retrieval, but you can further adjust this with MAX_SIZE:

```php
class Product extends Model
{
    const MAX_SIZE = 10000; // Set your preferred max size  // [!code highlight]
    protected $connection = 'elasticsearch';
}
```

For processing large datasets, consider implementing [chunking](/eloquent/chunking) to iterate over all records efficiently.

## Handling Empty Text Fields

By default, empty text fields are not indexed and thus not searchable. To facilitate searches for empty values, you have two main strategies:

[See Querying models with empty strings](/eloquent/querying-models.html#empty-strings-values)

- Omit the empty field during document saving/updating, then utilize [whereNull](/eloquent/querying-models.html#wherenull) for searching.
- Define a default null value in your index schema, ex:

```php
Schema::create('products', function (IndexBlueprint $index) {
    $index->keyword('color')->nullValue('NA');
});
```

::: details Explain
The nullValue method sets a default value for the field when it is empty as a searchable string, 'NA' in this case.
:::

## Sorting by Text Fields

Elasticsearch cannot sort by fields indexed as text due to their tokenized nature. If you try it will throw an 'All shards failed' error. To enable sorting:

- Use keyword type for fields where sorting is a priority and full-text search is not needed.
- For fields requiring both full-text search and sorting, define them with multi-fields in your schema, ensuring text and keyword types are included. Use the keyword type for sorting `(orderBy('field.keyword')`).

```php
Schema::create('contacts', function (IndexBlueprint $index) {
    $index->text('name');
    $index->keyword('name');
});
```

## Save Operations and Refresh

In Elasticsearch, a refresh operation makes newly indexed documents searchable and is synchronous by default in this plugin, ensuring immediate data availability post-write. This could introduce latency in response times, not ideal in all situations.

To bypass this, use `saveWithoutRefresh()` when immediate searchability of the newly indexed document is not critical, reducing write latency.

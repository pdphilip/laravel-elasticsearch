# Full-Text Search
The Laravel-Elasticsearch integration introduces an intuitive and powerful approach to full-text search, extending the familiar Eloquent syntax with Elasticsearch's rich querying capabilities. This section outlines how to construct and execute search queries that span across all or selected fields within an index, leveraging various search techniques such as term queries, boosting, fuzzy searches, and regex.

## The Search Query
Unlike the traditional `where()->get()` method which operates on specific fields, the search method is designed to **perform a full-text search across multiple fields**, offering a broader search scope.
```php
$results = MyModel::term('XYZ')->search();
```

## Term Search: `term()`
To search for a single term across all fields in an index:
```php
$results = Book::term('Eric')->search();
```
::: details Explain
Search for 'Eric' across all fields
:::

## Multiple Terms
Combining terms with logical operators (AND/OR):
```php
$results = Book::term('Eric')->orTerm('Lean')->andTerm('Startup')->search();
```
::: details Explain
Search for books that contain 'Eric' or both 'Lean' and 'Startup'
:::

## Phrase Search: `phrase()`
A **phrase** is a sequence of words that must appear in the same order as the search query. This is useful for finding exact matches or specific sequences of words.

To search for a phrase across all fields in an index:
```php
$results = Book::phrase('Lean Startup')->search();
```
::: details Explain
Search for the phrase 'Lean Startup' across all fields
:::

## Multiple Phrases
Combining phrases with logical operators (AND/OR):

`orPhrase($phrase)`: Searches for documents containing either the original phrase or the specified phrase.
```php
$results = Book::phrase('United States')->orPhrase('United Kingdom')->search();
```
::: details Explain
Search for books that contain either 'United States' or 'United Kingdom', phrases like 'United Emirates' will not be included.
:::

`andPhrase($phrase)`: Searches for documents containing both the original phrase and the specified phrase.
```php
$results = Book::phrase('Lean Startup')->andPhrase('Eric Ries')->search();
```
::: details Explain
Search for books that contain both 'Lean Startup' and 'Eric Ries'
:::

## Boosting Terms
Boosting enhances the relevance score of certain terms:

```php
$results = Book::term('Eric', 2)->orTerm('Lean Startup')->search();
```
::: details Explain
'Eric' is boosted, making it more significant in the search results. The term Lean AND Startup are searched for independently (Not the phrase 'Lean Startup').
:::

## Searching Selected Fields
Limiting the search to specific fields:

```php
$results = Book::term('Eric')->fields(['title', 'author', 'description'])->search();
```
::: details Explain
Search only within the 'title', 'author', and 'description' fields.
:::

## Minimum Should Match
Configures the minimum amount of terms that are required to match

```php
$results = Book::term('Eric')->orTerm('Lean')->orTerm('Startup')->field('title')->field('author')->minShouldMatch(2)->search();
```
::: details Explain
Requires at least 2 of the 3 terms to match in the specified fields
:::

## Minimum Score
Sets a minimum relevance score for results:

```php
$results = Book::term('Eric')->field('title', 3)->field('author', 2)->field('description')->minScore(2.1)->search();
```
::: details Explain
Only includes results with a score higher than 2.1
:::

## Combining Search with Eloquent Queries
Search queries can be blended with standard Eloquent queries:

```php
$results = Book::term('David')->field('title', 3)->field('author', 2)->field('description')->minScore(2.1)->where('is_active', true)->search();
```
::: details Explain
Combines a full-text search with a filter on the 'is_active' field
:::

## Fuzzy Searches
Fuzzy searches allow for matching terms that are similar to the search term:

```php
$results = Book::fuzzyTerm('quikc')->orFuzzyTerm('brwn')->andFuzzyTerm('foks')->search();
```
::: details Explain
Performs a fuzzy search to account for minor typos or variations
:::

## Regular Expressions
Leverage Elasticsearch's support for regex in your searches:

```php
// Uses regex patterns to match documents
$results = Book::regEx('joh?n(ath[oa]n)')->andRegEx('doey*')->search();
```

## Highlighting
Highlighting allows you to display search results with the matched terms highlighted:

`highlight($fields = [], $preTag = '<em>', $postTag = '</em>', $globalOptions = [])`

The `highlighted` results are stored in the model's metadata and can be accessed via a built-in model attribute using:
* `$model->searchHighlights`: returns on object with the found highlights for each field.
* `$model->searchHighlightsAsArray`: returns an associative array with the found highlights for each field.

The values of the highlights are always in an array, even if there is only one fragment.
::: tip In `$model->searchHighlights` only the top level fields are the object keys (as is with normal model attributes). Ex: `$model->searchHighlights->manufacturer['location']['country']`

The array in `$model->searchHighlightsAsArray` is a flat associative array with the field names (in dot notation) as keys. Ex: `$model->searchHighlights[manufacturer.location.country]`
:::

```php
$highlights = [];
$products = Product::term('espresso')->highlight()->search();
foreach ($products as $product) {
    $highlights[$product->_id] = $product->searchHighlights;
}
```
::: details Explain
Search for products containing `espresso` in any field. All hits on `espresso` will be stored in the highlights metadata as an array under the field where the hit occurred.
:::

You can filter the fields to highlight:
```php
$highlights = [];
$products = Product::term('espresso')->highlight(['description'],'<strong>','</strong>')->search();
foreach ($products as $product) {
    $highlights[$product->_id] = $product->searchHighlights->description ?? [];
}
```
::: detail Explain
Search for products containing `espresso` in any field. Only hits on `espresso` in the `description` field will highlighted and wrapped in `strong` tags. All results will be returned.
:::
```php
$highlightFields = [
    'name' => [
        'pre_tags'  => ['<span class="text-primary-500">'],
        'post_tags' => ['</span>'],
    ],
    'description' => [
        'pre_tags'  => ['<span class="text-secondary-500">'],
        'post_tags' => ['</span>'],
    ],
    'manufacturer.name' => [
        'pre_tags'  => ['<span class="text-sky-500">'],
        'post_tags' => ['</span>'],
    ],
];
$highlights = [];
$products = Product::term('espresso')->highlight($highlightFields)->search();
foreach ($products as $product) {
    $highlights[$product->_id]['name'] = $product->searchHighlights->name ?? [];
    $highlights[$product->_id]['description'] = $product->searchHighlights->description ?? [];
    $highlights[$product->_id]['manufacturer'] = $product->searchHighlights->manufacturer['name'] ?? [];
}
```
::: detail Explain
Search for products containing `espresso` in any field. Hits on `espresso` in the `name` field will be highlighted with a primary color, hits in the description field will be highlighted with a secondary color, and any hits in the manufacturer.name field will be highlighted with a sky color.
:::

Global options can be set for all fields:
```php
$options = [
    'number_of_fragments' => 3,
    'fragment_size' => 150,
];
$highlights = [];
$products = Product::term('espresso')->highlight([],'<em>','</em>',$options)->search();
foreach ($products as $product) {
    $highlights[$product->_id] = $product->searchHighlights;
}
```
::: detail Explain
Search for products containing 'espresso' in any field. A maximum of 3 fragments will be returned for each field, with each fragment being a maximum of 150 characters long.
:::

### `$model->withHighlights->field`
This built in attribute will get all the model's data, parse any user defined mutators, then overwrite any fields that have highlighted data. This is useful when you want to display the highlighted data in a view.

::: tip This is not an instance of the model, so you cannot call any model methods on it (like save, update, etc). This is intentional to avoid accidentally saving the highlighted data to the database.
:::

::: tip For multiple fragments, the values are concatenated with `.....`
:::
```bladehtml
@foreach ($products as $product)
    <tr>
        <td>{!! $product->withHighlights->name !!}</td>
        <td>{!! $product->withHighlights->description !!}</td>
    </tr>
@endforeach
```

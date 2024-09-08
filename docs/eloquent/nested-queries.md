# Nested Queries

Nested queries are a powerful feature of Elasticsearch that allows you to search within nested objects or arrays. This feature is especially useful when working with complex data structures that require deep search capabilities.

## WhereNestedObject

This method allows you to query nested objects within an Elasticsearch document. Nested fields are useful when you need to store large arrays of objects without flattening them into a single array or overindexing the parent index.

```php
$posts = BlogPost::whereNestedObject('comments', function (Builder $query) {
    $query->where('comments.country', 'Peru')->where('comments.likes', 5);
})->get();
```

::: details Explain
This will return all the blog posts where the comments field contains an object with the country field set to 'Peru' and the likes field set to 5.
:::

::: tip The field **must be of type nested for the `whereNestedObject` method otherwise you will get no results**, make sure to set the nested field in your migration, ex:

```php
Schema::create('blog_posts', function (IndexBlueprint $index) {
    $index->nested('comments');
});
```

:::

You are **free to exclude the parent nested field** in the closure, for example:

```php
$posts = BlogPost::whereNestedObject('comments', function (Builder $query) {
    $query->where('country', 'Peru')->where('likes', 5);
    // 'country' points to 'comments.country'
    // 'likes' points to 'comments.likes'
})->get();
```

## WhereNotNestedObject

Similar to whereNestedObject, this method allows you to query nested objects within an Elasticsearch document. However, it **excludes documents that match the specified nested object query**.

```php
$posts = BlogPost::whereNotNestedObject('comments', function (Builder $query) {
    $query->where('comments.country', 'Peru');
})->get();
```

::: details Explain
This will return all the blog posts where there are no comments from Peru.
:::

## Order By Nested Field

This method allows you to order the results of a query by a nested field. This is useful when you need to sort the results of a query based on a nested field.

Parameters scope: `orderByNested($field, $direction = 'asc', $mode = 'min')`

```php
$posts = BlogPost::where('status', 5)->orderByNested('comments.likes', 'desc', 'sum')->limit(5)->get();
```

::: details Explain
This will return the 5 blog posts with the highest sum of likes in the comments field where the status field is set to 5.
:::

## Filtering Nested Values

### Format: `queryNested($field, Closure $closure)`

This method allows you to **filter the values of a nested field** by passing in a query closure that will be applied to the nested values.

Internally, the package will set an `inner_hits` query to get the nested values that match the closure query, and map them to the parent document.

### Example:

#### 1. No Filtering:

```php
$posts = BlogPost::where('status', 5)->orderBy('created_at')->first();
```

returns:

```json
{
  "_id": "1IiLKG38BCOXW3U9a4zcn",
  "title": "My first post",
  "status": 5,
  "comments": [
    {
      "name": "Damaris Ondricka",
      "comment": "Quia quis facere cupiditate unde natus dolorem. Quia voluptatem in nam occaecati. Veritatis libero neque vitae.",
      "country": "Peru",
      "likes": 5
    },
    {
      "name": "Cole Beahan",
      "comment": "Officia ut dolorem itaque sapiente repellendus consequatur. Voluptas veniam quis eligendi. Aliquid voluptatem reiciendis ut.",
      "country": "Sweden",
      "likes": 0
    },
    {
      "name": "April Von",
      "comment": "Repudiandae rem aspernatur neque molestiae voluptatibus ut aut. Animi dolor id voluptas. Blanditiis a est nobis voluptatem sed sed illum esse.",
      "country": "Switzerland",
      "likes": 10
    },
    {
      "name": "Ella Ruecker",
      "comment": "Et deleniti ab cumque nobis ut ullam. Exercitationem qui sequi voluptatem delectus sunt nobis. Vel libero nihil quas inventore omnis. Harum corrupti consequatur quibusdam ut.",
      "country": "UK",
      "likes": 5
    },
    {
      "name": "Mabelle Schinner",
      "comment": "Aliquid molestiae quas vitae ipsam neque nam sed. Facere blanditiis repellendus sequi autem. Explicabo cupiditate porro quia animi ut minus tempora ut.",
      "country": "Switzerland",
      "likes": 7
    }
  ]
}
```

#### 2. Filter comments from Switzerland:

```php
$posts = BlogPost::where('status', 5)->queryNested('comments', function ($query) {
        $query->where('country', 'Switzerland'); //or comments.country
    })->orderBy('created_at')->first();
```

returns:

```json
{
  "_id": "1IiLKG38BCOXW3U9a4zcn",
  "title": "My first post",
  "status": 5,
  "comments": [
    {
      "name": "April Von",
      "comment": "Repudiandae rem aspernatur neque molestiae voluptatibus ut aut. Animi dolor id voluptas. Blanditiis a est nobis voluptatem sed sed illum esse.",
      "country": "Switzerland",
      "likes": 10
    },
    {
      "name": "Mabelle Schinner",
      "comment": "Aliquid molestiae quas vitae ipsam neque nam sed. Facere blanditiis repellendus sequi autem. Explicabo cupiditate porro quia animi ut minus tempora ut.",
      "country": "Switzerland",
      "likes": 7
    }
  ]
}
```

#### 3. Filter comments from Switzerland ordered by likes:

```php
$posts = BlogPost::where('status', 5)->queryNested('comments', function ($query) {
        $query->where('country', 'Switzerland')->orderBy('likes');
    })->orderBy('created_at')->first();
```

returns:

```json
{
  "_id": "1IiLKG38BCOXW3U9a4zcn",
  "title": "My first post",
  "status": 5,
  "comments": [
    {
      "name": "Mabelle Schinner",
      "comment": "Aliquid molestiae quas vitae ipsam neque nam sed. Facere blanditiis repellendus sequi autem. Explicabo cupiditate porro quia animi ut minus tempora ut.",
      "country": "Switzerland",
      "likes": 7
    },
    {
      "name": "April Von",
      "comment": "Repudiandae rem aspernatur neque molestiae voluptatibus ut aut. Animi dolor id voluptas. Blanditiis a est nobis voluptatem sed sed illum esse.",
      "country": "Switzerland",
      "likes": 10
    }
  ]
}
```

#### 4. Filter comments with likes greater than 5:

```php
$posts = BlogPost::where('status', 5)->queryNested('comments', function ($query) {
        $query->where('likes', '>', 5);
    })->orderBy('created_at')->first();
```

returns:

```json
{
  "_id": "1IiLKG38BCOXW3U9a4zcn",
  "title": "My first post",
  "status": 5,
  "comments": [
    {
      "name": "April Von",
      "comment": "Repudiandae rem aspernatur neque molestiae voluptatibus ut aut. Animi dolor id voluptas. Blanditiis a est nobis voluptatem sed sed illum esse.",
      "country": "Switzerland",
      "likes": 10
    },
    {
      "name": "Mabelle Schinner",
      "comment": "Aliquid molestiae quas vitae ipsam neque nam sed. Facere blanditiis repellendus sequi autem. Explicabo cupiditate porro quia animi ut minus tempora ut.",
      "country": "Switzerland",
      "likes": 7
    }
  ]
}
```

#### 5. Filter comments with likes greater than or equal to 5, limit 2:

```php
$posts = BlogPost::where('status', 5)->queryNested('comments', function ($query) {
        $query->where('likes', '>=', 5)->limit(2);
    })->orderBy('created_at')->first();
```

returns:

```json
{
  "_id": "1IiLKG38BCOXW3U9a4zcn",
  "title": "My first post",
  "status": 5,
  "comments": [
    {
      "name": "Damaris Ondricka",
      "comment": "Quia quis facere cupiditate unde natus dolorem. Quia voluptatem in nam occaecati. Veritatis libero neque vitae.",
      "country": "Peru",
      "likes": 5
    },
    {
      "name": "April Von",
      "comment": "Repudiandae rem aspernatur neque molestiae voluptatibus ut aut. Animi dolor id voluptas. Blanditiis a est nobis voluptatem sed sed illum esse.",
      "country": "Switzerland",
      "likes": 10
    }
  ]
}
```

#### Note on Nested limits:

The default max limit for Elasticsearch on nested values when using `inner_hits` is `100`. Trying to set a limit higher than that will result in an error.

You can change the limit by setting the `max_inner_result_window` value in your Elasticsearch configuration.

```php
 Schema::create('blog_posts', function (IndexBlueprint $index) {
    $index->nested('comments');
    $index->settings('max_inner_result_window', 200);
});
```

Then you can:

```php
$posts = BlogPost::where('status', 5)->queryNested('comments', function ($query) {
        $query->orderByDesc('likes')->limit(200)
    })->orderBy('created_at')->first();
```

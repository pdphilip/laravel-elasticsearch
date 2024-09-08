# Extending the Base model

In this section, we'll dive into how to hook your Laravel models into Elasticsearch by extending the base model, allowing you to work with Elasticsearch indices as if they were regular Eloquent models.

## Extending Your Model

Every model you want to index in Elasticsearch should extend the `PDPhilip\Elasticsearch\Eloquent\Model`. **This base model extends Laravel's Eloquent model, so you can use it just like you would any other Eloquent model.**

```php
namespace App\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;

/**
* @mixin \Eloquent
*/
class Product extends Model
{
    protected $connection = 'elasticsearch';
}
```

Just like a regular model, the index name will be inferred from the name of the model. In this example, the corresponding index for the `Product` model is `products`. In most cases, the elasticsearch connection won't be the default connection, and you'll need to include `protected $connection = 'elasticsearch'` in your model.

## Model properties

### `$index`

To change the inferred index name, pass in the `$index` property:

```php{10}
namespace App\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;
/**
* @mixin \Eloquent
*/
class Product extends Model
{
    protected $connection = 'elasticsearch';
    protected $index = 'my_products';
}
```

::: tip You can also set [Dynamic indices](/eloquent/dynamic-indices) which will allow you to use the same model for multiple indices.
:::

### Timestamps

By default, the base model will automatically set the `created_at` and `updated_at` fields. As is the case with Eloquent, you can disable this by setting the CREATED_AT and UPDATED_AT constants to null in your model

```php{10-11}
namespace App\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;
/**
* @mixin \Eloquent
*/
class Product extends Model
{
    protected $connection = 'elasticsearch';
    const CREATED_AT = null;
    const UPDATED_AT = null;
}

```

::: tip You can also set [Dynamic indices](/eloquent/dynamic-indices) which will allow you to use the same model for multiple indices.
:::

### Limits

Elasticsearch's default search limit is to return 10 collections, however, this Laravel-Elasticsearch integration defaults to 1,000. You can change this default limit by setting the `MAX_SIZE` property in your model.

```php{7}
use PDPhilip\Elasticsearch\Eloquent\Model;
/**
 * @mixin \Eloquent
 */
class Product extends Model
{
    const MAX_SIZE = 10000;
    protected $connection = 'elasticsearch';
}
```

::: tip You can also set [Dynamic indices](/eloquent/dynamic-indices) which will allow you to use the same model for multiple indices.
:::

## Mutators & Casting

**You can use mutators and casting in your models just like you would with any other Eloquent model.**

In the context of the Laravel-Elasticsearch integration, the foundational BaseModel **inherits all the features of Laravel's Eloquent model**, including mutators and casting. This means you can define mutators and casts like you would with any other Eloquent model.

For a comprehensive understanding of how to implement and use attribute mutators and casts within your models, refer to the official Laravel documentation on Eloquent Mutators & Casting: [Laravel - Eloquent: Mutators & Casting](https://laravel.com/docs/11.x/eloquent-mutators).

## Query Meta

Once a query is executed, the query meta is stored in the model instance. You can access the query meta by calling the getMeta() method on the model instance.

```php
$product = Product::where('color', 'green')->first();

return $product->getMeta();
```

::: tip You can also set [Dynamic indices](/eloquent/dynamic-indices) which will allow you to use the same model for multiple indices.
:::

returns:

```json
{
  "_index": "es11_products",
  "_id": "jbTwDI8BXOrz985smeb_",
  "_score": 1.9423501,
  "_query": {
    "took": 2,
    "timed_out": false,
    "total": 100,
    "max_score": 1.9423501,
    "shards": {
      "total": 1,
      "successful": 1,
      "skipped": 0,
      "failed": 0
    }
  }
}
```

# Dynamic Indices

In certain scenarios, it may be necessary to distribute a model's data across multiple Elasticsearch indices to optimize performance, manage data volume, or comply with specific data storage requirements. The Laravel-Elasticsearch integration accommodates this need through the support for dynamic indices, allowing a single model to interact with a range of indices following a consistent naming pattern.

## Implementing Dynamic Indices

To enable dynamic indexing, define the $index property in your model using a wildcard pattern. This approach allows the model to recognize and interact with multiple indices that share a common prefix.

```php
namespace App\Models;

use PDPhilip\Elasticsearch\Eloquent\Model as Eloquent;

class PageHit extends Eloquent
{
    protected $connection = 'elasticsearch';
    // Define a dynamic index pattern
    protected $index = 'page_hits_*'; // Dynamic index pattern // [!code highlight]
}
```

In this example, the PageHit model is configured to work with indices that match the pattern `page_hits_*`, enabling operations across all indices starting with `page_hits_`.

## Querying Across Dynamic Indices

When querying a model with dynamic indices, the query will span all indices that match the defined pattern, allowing for aggregated data retrieval. This means that you can query your model without specifying the exact index, and the query will automatically search across all matching indices.

```php
$pageHits = PageHit::where('page_id', 1)->get();
```

::: details Explain
Retrieve page hits for page_id 1 across all 'page_hits\_\*' indices
:::

## Creating Records with Dynamic Indices

When creating new records, **you must explicitly set the target index** using the `setIndex` method to specify the exact index where the record should be stored.

```php
$pageHit = new PageHit;
$pageHit->page_id = 4;
$pageHit->ip = $someIp;
// Set the specific index for the new record
$pageHit->setIndex('page_hits_' . date('Y-m-d')); // [!code highlight]
$pageHit->save();

```

::: details Explain
This pattern ensures that new records are stored in the appropriate index based on your application's logic, such as using the current date to determine the index name.
:::

## Retrieving the Current Record's Index

Each model instance associated with a dynamic index retains knowledge of the specific index it pertains to. In some cases you may need to know the exact index for a given record. To retrieve the current record's index, use the `getRecordIndex` method

```php
$indexName = $pageHit->getRecordIndex();
```

### Searching Within a Specific Dynamic Index

To constrain a search to a specific index within the range of dynamic indices, set the desired index using `setIndex` before constructing your query.

```php
// Instantiate the model and set the specific index to search within
$model = new PageHit;
$model->setIndex('page_hits_2023-01-01');
// Perform the query within the specified index
$pageHits = $model->where('page_id', 3)->get();
```

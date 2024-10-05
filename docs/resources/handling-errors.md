# Handling Errors

This package returns errors that are more readable than Elasticsearch's default responses. And embeds helpful metadata to help elucidate the issue.

## QueryException

The `QueryException` class will be thrown when a query fails to be executed by the Elasticsearch client. This will account for most errors.

The message will be extracted from the verbose Elasticsearch response and the details will be stored in the Exception's `details` property.

### Example

Where the `manufacturer.location` field has not been mapped as a `geo` field:

```php
use PDPhilip\Elasticsearch\DSL\exceptions\QueryException;

try {
    return Product::where('status', 2)->filterGeoPoint('manufacturer.location', '100km', [0, 0])->get();
} catch (QueryException $e) {
    return $e->getDetails();
}
```

Error Message will be: `400 Bad Request: all shards failed - failed to find geo field [manufacturer.location]`

And `$e->getDetails()` returns:

```json
{
  "error": "400 Bad Request: all shards failed - failed to find geo field [manufacturer.location]",
  "details": {
    "error": {
      "root_cause": [
        {
          "type": "query_shard_exception",
          "reason": "failed to find geo field [manufacturer.location]",
          "index_uuid": "kJgCkVJ3RfSuF8G5OaROEg",
          "index": "es11_products"
        }
      ],
      "type": "search_phase_execution_exception",
      "reason": "all shards failed",
      "phase": "query",
      "grouped": true,
      "failed_shards": [
        {
          "shard": 0,
          "index": "es11_products",
          "node": "FOKbXkxPT56cxxfvtbgV1g",
          "reason": {
            "type": "query_shard_exception",
            "reason": "failed to find geo field [manufacturer.location]",
            "index_uuid": "kJgCkVJ3RfSuF8G5OaROEg",
            "index": "es11_products"
          }
        }
      ]
    },
    "status": 400
  },
  "code": 400,
  "exception": "Elastic\\Elasticsearch\\Exception\\ClientResponseException",
  "query": "returnSearch",
  "params": {
    "index": "es11_products",
    "body": {
      "query": {
        "bool": {
          "must": [
            {
              "match": {
                "status": 2
              }
            }
          ],
          "filter": {
            "geo_distance": {
              "distance": "100km",
              "manufacturer.location": {
                "lat": 0,
                "lon": 0
              }
            }
          }
        }
      }
    },
    "size": 1000
  },
  "original": "400 Bad Request: {\"error\":{\"root_cause\":[{\"type\":\"query_shard_exception\",\"reason\":\"failed to find geo field [manufacturer.location]\",\"index_uuid\":\"kJgCkVJ3RfSuF8G5OaROEg\",\"index\":\"es11_products\"}],\"type\":\"search_phase_execution_exception\",\"reason\":\"all shards failed\",\"phase\":\"query\",\"grouped\":true,\"failed_shards\":[{\"shard\":0,\"index\":\"es11_products\",\"node\":\"FOKbXkxPT56cxxfvtbgV1g\",\"reason\":{\"type\":\"query_shard_exception\",\"reason\":\"failed to find geo field [manufacturer.location]\",\"index_uuid\":\"kJgCkVJ3RfSuF8G5OaROEg\",\"index\":\"es11_products\"}}]},\"status\":400}"
}
```

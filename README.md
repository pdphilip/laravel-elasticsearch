<img align="left" width="70" height="70" src="https://cdn.snipform.io/pdphilip/elasticsearch/laravel-x-es.png">

# Laravel-Elasticsearch

[![Latest Stable Version](http://img.shields.io/github/release/pdphilip/laravel-elasticsearch.svg)](https://packagist.org/packages/pdphilip/elasticsearch)
[![Total Downloads](http://img.shields.io/packagist/dm/pdphilip/elasticsearch.svg)](https://packagist.org/packages/pdphilip/elasticsearch)

### An Elasticsearch implementation of Laravel's Eloquent ORM

This package extends Laravel's Eloquent model and query builder with seamless integration of Elasticsearch functionalities. Designed to feel native to Laravel, this package enables you to work with Eloquent models while leveraging the
powerful search and analytics capabilities of Elasticsearch.

```php
$logs = UserLog::where('type', UserLogType::LOGIN)->where('created_at','>=',Carbon::now()->subDays(30))->get();
```

### Read the [Documentation](https://elasticsearch.pdphilip.com/)

---

## Installation

### Maintained versions (Elasticsearch 8.x):

Please note: Only **version 3** of the package will be maintained.

**Laravel 11.x (main):**

```bash
composer require pdphilip/elasticsearch
```

| Laravel Version | Command                                          | Maintained |
|-----------------|--------------------------------------------------|------------|
| Laravel 11.x    | `composer require pdphilip/elasticsearch `       | ✅          |
| Laravel 10.x    | `composer require pdphilip/elasticsearch:~3.10 ` | ✅          |
| Laravel 9.x     | `composer require pdphilip/elasticsearch:~3.9`   | ✅          |
| Laravel 8.x     | `composer require pdphilip/elasticsearch:~3.8`   | ✅          |

### Unmaintained versions (Elasticsearch 8.x):

| Laravel Version   | Command                                        | Maintained |
|-------------------|------------------------------------------------|------------|
| Laravel 7.x       | `composer require pdphilip/elasticsearch:~2.7` | ❌          |
| Laravel 6.x (5.8) | `composer require pdphilip/elasticsearch:~2.6` | ❌          |

### Unmaintained versions (Elasticsearch 7.x):

| Laravel Version   | Command                                        | Maintained |
|-------------------|------------------------------------------------|------------|
| Laravel 9.x       | `composer require pdphilip/elasticsearch:~1.9` | ❌          |
| Laravel 8.x       | `composer require pdphilip/elasticsearch:~1.8` | ❌          |
| Laravel 7.x       | `composer require pdphilip/elasticsearch:~1.7` | ❌          |
| Laravel 6.x (5.8) | `composer require pdphilip/elasticsearch:~1.6` | ❌          |

## Configuration

1. Set up your `.env` with the following Elasticsearch settings:

```ini
ES_AUTH_TYPE=http
ES_HOSTS="http://localhost:9200"
ES_USERNAME=
ES_PASSWORD=
ES_CLOUD_ID=
ES_API_ID=
ES_API_KEY=
ES_SSL_CA=
ES_INDEX_PREFIX=my_app
# prefix will be added to all indexes created by the package with an underscore
# ex: my_app_user_logs for UserLog.php model
ES_SSL_CERT=
ES_SSL_CERT_PASSWORD=
ES_SSL_KEY=
ES_SSL_KEY_PASSWORD=
# Options
ES_OPT_ID_SORTABLE=false
ES_OPT_VERIFY_SSL=true
ES_OPT_RETRIES=
ES_OPT_META_HEADERS=true
```

For multiple nodes, pass in as comma-separated:

```ini
ES_HOSTS="http://es01:9200,http://es02:9200,http://es03:9200"
```

<details>
<summary>Example cloud config .env: (Click to expand)</summary>

```ini
ES_AUTH_TYPE=cloud
ES_HOSTS="https://xxxxx-xxxxxx.es.europe-west1.gcp.cloud.es.io:9243"
ES_USERNAME=elastic
ES_PASSWORD=XXXXXXXXXXXXXXXXXXXX
ES_CLOUD_ID=XXXXX:ZXVyb3BlLXdl.........SQwYzM1YzU5ODI5MTE0NjQ3YmEyNDZlYWUzOGNkN2Q1Yg==
ES_API_ID=
ES_API_KEY=
ES_SSL_CA=
ES_INDEX_PREFIX=my_app
```

</details>

2. In `config/database.php`, add the elasticsearch connection:

```php
'elasticsearch' => [
    'driver'       => 'elasticsearch',
    'auth_type'    => env('ES_AUTH_TYPE', 'http'), //http or cloud
    'hosts'        => explode(',', env('ES_HOSTS', 'http://localhost:9200')),
    'username'     => env('ES_USERNAME', ''),
    'password'     => env('ES_PASSWORD', ''),
    'cloud_id'     => env('ES_CLOUD_ID', ''),
    'api_id'       => env('ES_API_ID', ''),
    'api_key'      => env('ES_API_KEY', ''),
    'ssl_cert'     => env('ES_SSL_CA', ''),
    'ssl'          => [
        'cert'          => env('ES_SSL_CERT', ''),
        'cert_password' => env('ES_SSL_CERT_PASSWORD', ''),
        'key'           => env('ES_SSL_KEY', ''),
        'key_password'  => env('ES_SSL_KEY_PASSWORD', ''),
    ],
    'index_prefix' => env('ES_INDEX_PREFIX', false),
    'options'      => [
        'allow_id_sort'    => env('ES_OPT_ID_SORTABLE', false),
        'ssl_verification' => env('ES_OPT_VERIFY_SSL', true),
        'retires'          => env('ES_OPT_RETRIES', null),
        'meta_header'      => env('ES_OPT_META_HEADERS', true),
    ],
    'query_log'    => [
        'index'      => false, //Or provide a name for the logging index ex: 'laravel_query_logs'
        'error_only' => true, //If false, then all queries are logged if the query_log index is set
    ],
],
```

### 3. If packages are not autoloaded, add the service provider:

For **Laravel 10 and below**:

```php
//config/app.php
'providers' => [
    ...
    ...
    PDPhilip\Elasticsearch\ElasticServiceProvider::class,
    ...

```

For **Laravel 11**:

```php
//bootstrap/providers.php
<?php
return [
    App\Providers\AppServiceProvider::class,
    PDPhilip\Elasticsearch\ElasticServiceProvider::class,
];
```

Now, you're all set to use Elasticsearch with Laravel as if it were native to the framework.

---

# Documentation Links

## Getting Started

- [Installation](https://elasticsearch.pdphilip.com/#installation)
- [Configuration](https://elasticsearch.pdphilip.com/#configuration)

## Eloquent

- [The Base Model](https://elasticsearch.pdphilip.com/the-base-model)
- [Querying Models](https://elasticsearch.pdphilip.com/querying-models)
- [Saving Models](https://elasticsearch.pdphilip.com/saving-models)
- [Deleting Models](https://elasticsearch.pdphilip.com/deleting-models)
- [Ordering and Pagination](https://elasticsearch.pdphilip.com/ordering-and-pagination)
- [Distinct and GroupBy](https://elasticsearch.pdphilip.com/distinct)
- [Aggregations](https://elasticsearch.pdphilip.com/aggregation)
- [Chunking](https://elasticsearch.pdphilip.com/chunking)
- [Nested Queries](https://elasticsearch.pdphilip.com/nested-queries) **New in Version 3**
- [Elasticsearch Specific Queries](https://elasticsearch.pdphilip.com/es-specific)
- [Full-Text Search](https://elasticsearch.pdphilip.com/full-text-search)
- [Dynamic Indices](https://elasticsearch.pdphilip.com/dynamic-indices)

## Relationships

- [Elasticsearch to Elasticsearch](https://elasticsearch.pdphilip.com/es-es)
- [Elasticsearch to MySQL](https://elasticsearch.pdphilip.com/es-mysql)

## Schema/Index

- [Migrations](https://elasticsearch.pdphilip.com/migrations)
- [Re-indexing Process](https://elasticsearch.pdphilip.com/re-indexing)

---

# New in Version 3

### Nested Queries [(see)](https://elasticsearch.pdphilip.com/nested-queries)

This update introduces support for querying, sorting and filtering nested data

- [Nested Object Queries](https://elasticsearch.pdphilip.com/nested-queries#where-nested-object)
- [Order By Nested](https://elasticsearch.pdphilip.com/nested-queries#order-by-nested-field)
- [Filter Nested Values](https://elasticsearch.pdphilip.com/nested-queries#filtering-nested-values): Filters nested values of the parent collection

### New `Where` clauses

- [Phrase Matching](https://elasticsearch.pdphilip.com/es-specific#where-phrase): The enhancement in phrase matching capabilities allows for refined search precision, facilitating the targeting of exact word sequences within textual
  fields, thus improving search specificity
  and relevance.
- [Exact Matching](https://elasticsearch.pdphilip.com/es-specific#where-exact): Strengthening exact match queries enables more stringent search criteria, ensuring the retrieval of documents that precisely align with specified parameters.

### Sorting Enhancements

- [Ordering with ES features](https://elasticsearch.pdphilip.com/ordering-and-pagination#extending-ordering-for-elasticsearch-features): Includes modes and missing values for sorting fields.
- [Order by Geo Distance](https://elasticsearch.pdphilip.com/ordering-and-pagination#order-by-geo-distance)

### Saving Updates

- [First Or Create](https://elasticsearch.pdphilip.com/saving-models#first-or-create)
- [First Or Create without Refresh](https://elasticsearch.pdphilip.com/saving-models#first-or-create-without-refresh)

### Grouped Queries

- [Grouped Queries](https://elasticsearch.pdphilip.com/querying-models#grouped-queries): Queries can be grouped allowing multiple conditions to be nested within a single query block.

---

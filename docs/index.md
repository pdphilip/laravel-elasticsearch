# Laravel-Elasticsearch
## An Elasticsearch implementation of Laravel's Eloquent ORM
This package extends Laravel's Eloquent model and query builder with seamless integration of Elasticsearch functionalities. Designed to feel native to Laravel, this package enables you to work with Eloquent models while leveraging the powerful search and analytics capabilities of Elasticsearch.

Looking to use the [OpenSearch](https://opensearch.pdphilip.com/) version of this package? [Github](https://github.com/pdphilip/laravel-opensearch)

## Installation
### Maintained versions (Elasticsearch 8.x):
### Laravel 11 and 10 - main branch (tag 4.x):
| Laravel Version | Command | Maintained |
|-----------------|---------|------------|
| Laravel 10 & 11 | `composer require pdphilip/elasticsearch:~4` | ✅ |
| Laravel 9       | `composer require pdphilip/elasticsearch:~3.9` | ✅ |
| Laravel 8       | `composer require pdphilip/elasticsearch:~3.8` | ✅ |

### Unmaintained versions (Elasticsearch 8.x):
| Laravel Version      | Command                                        | Maintained |
|----------------------|------------------------------------------------|----------|
| Laravel 7.x          | `composer require pdphilip/elasticsearch:~2.7` | ❌         |
| Laravel 6.x (5.8)    | `composer require pdphilip/elasticsearch:~2.6` | ❌        |

### Unmaintained versions (Elasticsearch 7.x):
| Laravel Version      | Command                                        | Maintained |
|----------------------|------------------------------------------------|------------|
| Laravel 9.x          | `composer require pdphilip/elasticsearch:~1.9` | ❌         |
| Laravel 8.x          | `composer require pdphilip/elasticsearch:~1.8` | ❌         |
| Laravel 7.x          | `composer require pdphilip/elasticsearch:~1.7` | ❌         |
| Laravel 6.x (5.8)    | `composer require pdphilip/elasticsearch:~1.6` | ❌         |

## Configuration
### 1. Set up your `.env` with the following Elasticsearch settings:
```dotenv
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
ES_ERROR_INDEX=
```

For multiple nodes, pass in as comma-separated:
```dotenv
ES_HOSTS="http://es01:9200,http://es02:9200,http://es03:9200"
```

::: details Cloud config .env example
1.5: In config/database.php, add the elasticsearch connection:
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
    'error_log_index' => env('ES_ERROR_INDEX', false),
],
```
:::

### 2. If packages are not autoloaded, add the service provider:
For **Laravel 10 and below**:

```php
//config/app.php
'providers' => [
    ...
    ...
    PDPhilip\Elasticsearch\ElasticServiceProvider::class, // [!code highlight]
    ...
```
For **Laravel 11**:
```php
//bootstrap/providers.php
<?php
return [
    App\Providers\AppServiceProvider::class,
    PDPhilip\Elasticsearch\ElasticServiceProvider::class, // [!code highlight]
];
```
Now, you're all set to use Elasticsearch with Laravel as if it were native to the framework.

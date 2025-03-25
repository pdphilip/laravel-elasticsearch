<img
src="https://cdn.snipform.io/pdphilip/elasticsearch/laravel-es-banner.png"
alt="Laravel Elasticsearch"
/>

[![Latest Stable Version](http://img.shields.io/github/release/pdphilip/laravel-elasticsearch.svg)](https://packagist.org/packages/pdphilip/elasticsearch)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/pdphilip/laravel-elasticsearch/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/pdphilip/laravel-elasticsearch/actions/workflows/run-tests.yml?query=branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/pdphilip/laravel-elasticsearch/phpstan.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/pdphilip/laravel-elasticsearch/actions/workflows/phpstan.yml?query=branch%3Amain++)
[![Total Downloads](http://img.shields.io/packagist/dm/pdphilip/elasticsearch.svg)](https://packagist.org/packages/pdphilip/elasticsearch)

## Laravel-Elasticsearch <br/> An Elasticsearch implementation of Laravel's Eloquent ORM

### The Power of Elasticsearch with Laravel's Eloquent

This package extends Laravel's Eloquent model and query builder with seamless integration of Elasticsearch functionalities. Designed to feel native to Laravel, this package enables you to work with Eloquent models while leveraging the
powerful search and analytics capabilities of Elasticsearch.

---

The Eloquent you already know:

```php
UserLog::where('created_at','>=',Carbon::now()->subDays(30))->get();
```

```php
UserLog::create([
    'user_id' => '2936adb0-b10d-11ed-8e03-0b234bda3e12',
    'ip' => '62.182.98.146',
    'location' => [40.7185,-74.0025],
    'country_code' => 'US',
    'status' => 1,
]);
```

```php
UserLog::where('status', 1)->update(['status' => 4]);
```

```php
UserLog::where('status', 4)->orderByDesc('created_at')->paginate(50);
```

```php
UserProfile::whereIn('country_code',['US','CA'])
    ->orderByDesc('last_login')->take(10)->get();
```

```php
UserProfile::where('state','unsubscribed')
    ->where('updated_at','<=',Carbon::now()->subDays(90))->delete();
```

Elasticsearch with Eloquent:

```php
UserProfile::searchTerm('Laravel')->orSearchTerm('Elasticsearch')->get();
```

```php
UserProfile::searchPhrasePrefix('loves espressos and t')->highlight()->get();
```

```php
UserProfile::whereMatch('bio', 'PHP')->get();
```

```php
UserLog::whereGeoDistance('location', '10km', [40.7185,-74.0025])->get();
```

```php
UserProfile::whereFuzzy('description', 'qick brwn fx')->get();
```

Built in Relationships (even to SQL models):

```php
UserLog::where('status', 1)->orderByDesc('created_at')->with('user')->get();
```

---

# Read the [Documentation](https://elasticsearch.pdphilip.com/)

## Installation

### Maintained versions (Elasticsearch 8.x):

**Laravel 10.x, 11.x & 12.x (main):**

```bash
composer require pdphilip/elasticsearch
```

| Laravel Version    | Command                                        | Maintained |
|--------------------|------------------------------------------------|------------|
| Laravel 10/11/12   | `composer require pdphilip/elasticsearch:~5 `  | ✅ Active   |
| Laravel 10/11 (v4) | `composer require pdphilip/elasticsearch:~4`   | 🛠️ LTS    |
| Laravel 9          | `composer require pdphilip/elasticsearch:~3.9` | 🛠️ LTS    |
| Laravel 8          | `composer require pdphilip/elasticsearch:~3.8` | 🛠️ LTS    |

### Unmaintained versions (Elasticsearch 8.x):

| Laravel Version   | Command                                        | Maintained |
|-------------------|------------------------------------------------|------------|
| Laravel 7.x       | `composer require pdphilip/elasticsearch:~2.7` | ❌ EOL      |
| Laravel 6.x (5.8) | `composer require pdphilip/elasticsearch:~2.6` | ❌ EOL      |

### Unmaintained versions (Elasticsearch 7.x):

| Laravel Version   | Command                                        | Maintained |
|-------------------|------------------------------------------------|------------|
| Laravel 9.x       | `composer require pdphilip/elasticsearch:~1.9` | ❌ EOL      |
| Laravel 8.x       | `composer require pdphilip/elasticsearch:~1.8` | ❌ EOL      |
| Laravel 7.x       | `composer require pdphilip/elasticsearch:~1.7` | ❌ EOL      |
| Laravel 6.x (5.8) | `composer require pdphilip/elasticsearch:~1.6` | ❌ EOL      |

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
ES_INDEX_PREFIX=my_app_
# prefix will be added to all indexes created by the package with an underscore
# ex: my_app_user_logs for UserLog model
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
ES_OPT_BYPASS_MAP_VALIDATION=false
ES_OPT_DEFAULT_LIMIT=1000

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
ES_INDEX_PREFIX=my_app_
ES_ERROR_INDEX=
```

</details>

2. In `config/database.php`, add the elasticsearch connection:

```php
'elasticsearch' => [
    'driver' => 'elasticsearch',
    'auth_type' => env('ES_AUTH_TYPE', 'http'), //http or cloud
    'hosts' => explode(',', env('ES_HOSTS', 'http://localhost:9200')),
    'username' => env('ES_USERNAME', ''),
    'password' => env('ES_PASSWORD', ''),
    'cloud_id' => env('ES_CLOUD_ID', ''),
    'api_id' => env('ES_API_ID', ''),
    'api_key' => env('ES_API_KEY', ''),
    'ssl_cert' => env('ES_SSL_CA', ''),
    'ssl' => [
        'cert' => env('ES_SSL_CERT', ''),
        'cert_password' => env('ES_SSL_CERT_PASSWORD', ''),
        'key' => env('ES_SSL_KEY', ''),
        'key_password' => env('ES_SSL_KEY_PASSWORD', ''),
    ],
    'index_prefix' => env('ES_INDEX_PREFIX', false),
    'options' => [
        'bypass_map_validation' => env('ES_OPT_BYPASS_MAP_VALIDATION', false),
        'logging' => env('ES_OPT_LOGGING', false),
        'ssl_verification' => env('ES_OPT_VERIFY_SSL', true),
        'retires' => env('ES_OPT_RETRIES', null),
        'meta_header' => env('ES_OPT_META_HEADERS', true),
        'default_limit' => env('ES_OPT_DEFAULT_LIMIT', true),
        'allow_id_sort' => env('ES_OPT_ID_SORTABLE', false),
    ],
],
```

### 3. If packages are not autoloaded, add the service provider:

For **Laravel 11 +**:

```php
//bootstrap/providers.php
<?php
return [
    App\Providers\AppServiceProvider::class,
    PDPhilip\Elasticsearch\ElasticServiceProvider::class,
];
```

For **Laravel 10 and below**:

```php
//config/app.php
'providers' => [
    ...
    ...
    PDPhilip\Elasticsearch\ElasticServiceProvider::class,
    ...

```

Now, you're all set to use Elasticsearch with Laravel as if it were native to the framework.

---

# Documentation Links

## Getting Started

- [Installation](https://elasticsearch.pdphilip.com/getting-started)
- [Configuration](https://elasticsearch.pdphilip.com/getting-started#configuration-guide)

### Eloquent

- [The Base Model](https://elasticsearch.pdphilip.com/eloquent/the-base-model)
- [Saving Models](https://elasticsearch.pdphilip.com/eloquent/saving-models)
- [Deleting Models](https://elasticsearch.pdphilip.com/eloquent/deleting-models)
- [Querying Models](https://elasticsearch.pdphilip.com/eloquent/querying-models)
- [Eloquent Queries](https://elasticsearch.pdphilip.com/eloquent/eloquent-queries)
- [ES Eloquent Queries](https://elasticsearch.pdphilip.com/eloquent/es-queries)
- [Cross Fields Search Queries](https://elasticsearch.pdphilip.com/eloquent/search-queries)
- [Aggregation Queries](https://elasticsearch.pdphilip.com/eloquent/aggregation)
- [Distinct and GroupBy Queries](https://elasticsearch.pdphilip.com/eloquent/distinct)
- [Nested Queries](https://elasticsearch.pdphilip.com/eloquent/nested-queries)
- [Ordering and Pagination](https://elasticsearch.pdphilip.com/eloquent/ordering-and-pagination)
- [Chunking](https://elasticsearch.pdphilip.com/eloquent/chunking)
- [Dynamic Indices](https://elasticsearch.pdphilip.com/eloquent/dynamic-indices)

### Relationships

- [Elasticsearch to Elasticsearch](https://elasticsearch.pdphilip.com/relationships/es-es)
- [Elasticsearch to SQL](https://elasticsearch.pdphilip.com/relationships/es-sql)

### Migrations: Schema/Index

- [Migrations](https://elasticsearch.pdphilip.com/schema/migrations)
- [Index Blueprint](https://elasticsearch.pdphilip.com/schema/index-blueprint)

### Misc

- [Mapping ES to Eloquent](https://elasticsearch.pdphilip.com/notes/elasticsearch-to-eloquent-map)

## Credits

- [David Philip](https://github.com/pdphilip)
- [@use-the-fork](https://github.com/use-the-fork)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

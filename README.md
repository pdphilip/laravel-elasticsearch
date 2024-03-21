<img align="left" width="70" height="70" src="https://cdn.snipform.io/pdphilip/elasticsearch/laravel-x-es.png">

# Laravel x Elasticsearch

This package extends Laravel's Eloquent model and query builder for Elasticsearch. The goal of this package is to use
Elasticsearch in laravel as if it were native to Laravel.

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

---

# New in version 3

- [whereExact()](https://elasticsearch.pdphilip.com/es-specific#where-exact)
- [wherePhrase()](https://elasticsearch.pdphilip.com/es-specific#where-phrase)
- [whereNestedObject()](https://elasticsearch.pdphilip.com/es-specific#where-nested-object)
- [whereNotNestedObject()](https://elasticsearch.pdphilip.com/es-specific#where-not-nested-object)
- [firstOrCreate()](https://elasticsearch.pdphilip.com/saving-models#first-or-create)
- [firstOrCreateWithoutRefresh()](https://elasticsearch.pdphilip.com/saving-models#first-or-create-without-refresh)
- [whereNested()](https://elasticsearch.pdphilip.com/querying-models#where-nested)

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
- [Aggregations](https://elasticsearch.pdphilip.com/aggregations)
- [Chunking](https://elasticsearch.pdphilip.com/chunking)
- [Elasticsearch Specific Queries](https://elasticsearch.pdphilip.com/es-specific)
- [Full-Text Search](https://elasticsearch.pdphilip.com/full-text-search)
- [Dynamic Indices](https://elasticsearch.pdphilip.com/dynamic-indices)

## Relationships

- [Elasticsearch to Elasticsearch](https://elasticsearch.pdphilip.com/es-es)
- [Elasticsearch to MySQL](https://elasticsearch.pdphilip.com/es-mysql)

## Schema/Index

- [Migrations](https://elasticsearch.pdphilip.com/migrations)
- [Re-indexing Process](https://elasticsearch.pdphilip.com/re-indexing)
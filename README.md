<img align="left" width="70" height="70" src="https://cdn.snipform.io/pdphilip/elasticsearch/laravel-x-es.png">

# Laravel x Elasticsearch

This package extends Laravel's Eloquent model and query builder with seamless integration of Elasticsearch functionalities. Designed to feel native to Laravel, this plugin enables you to work with Eloquent models while leveraging the
powerful search and analytics capabilities of Elasticsearch.

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

Next, [Configuration](https://elasticsearch.pdphilip.com/#configuration)

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
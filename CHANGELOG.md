# Changelog

All notable changes to this project will be documented in this file.

## \[unreleased\]

### 🚀 Features

- _(soft-model)_ Add factory and improve chunk, delete tests
- _(delete)_ Introduce dynamic deletion flag and remove refresh for tests
- _(models)_ Add hybrid Elasticsearch and SQLite models with enhanced relationships
- _(HiddenAnimal)_ Implement executeSchema method for dynamic schema operations
- _(models)_ \[**breaking**\] Enhance schema management and relationship handling
- _(elasticsearch)_ Implement customizable options store and improve query handling
- _(query)_ Extend query builder with push and pull array methods
- _(schema)_ Introduce extensive schema blueprint and property definition enhancements
- _(schema)_ Enhance schema tests and blueprint functionality
- _(query)_ Enhance query functionality with additional clauses and operations
- _(tests)_ Enhance query tests and refine query grammar
- _(elasticsearch)_ Add table suffix support for dynamic indices
- _(elasticsearch)_ Add support for save without refresh
- _(blueprint)_ Enhance blueprint functionality and add versatile traits
- _(tests)_ Enhance QueryParameter and GeoSpatial testing
- _(query)_ Enhance query builder with new aggregation capabilities
- _(query)_ Expand Elasticsearch query capabilities
- _(query)_ Refactor query builder and add nested query support
- _(search)_ Rework Elasticsearch search features
- _(search)_ Introduce highlight functionality for search queries
- _(query)_ Implement chunking and cursor-based pagination for large datasets
- _(schema)_ Implement dynamic index creation and enhance schema functionality
- _(schema)_ Introduce custom analyzers to Elasticsearch schema
- _(reindex)_ Introduce reindex functionality in Elasticsearch schema
- Pre rc1
- _(exceptions)_ Enhance QueryException with detailed error formatting

### 🐛 Bug Fixes

- _(DSL)_ Ensure unique IDs for Elasticsearch entities
- _(elasticsearch)_ \[**breaking**\] Correct primary key usage and relationships
- _(elasticsearch)_ Replace waitForPendingTasks with refreshIndex method
- _(query)_ Improve delete handling and add helpers
- _(exceptions)_ Enhance error message formatting and clean up comments
- _(tests)_ Correct test queries for case sensitivity and redundant operators
- _(exceptions)_ Correct error handling in QueryException for better debugging

### 🚜 Refactor

- _(exceptions, query)_ Restructure codebase and improve error handling
- _(schema)_ Enhance and modularize schema classes
- _(aggregations)_ Streamline aggregation handling and cursor functionality
- _(exceptions)_ Refine error handling in Elasticsearch exceptions
- _(query)_ Improve increment and decrement logic and clean unused methods
- _(DLS)_ Remove `Bridge` class and update `ElasticsearchModel` trait
- _(elasticsearch)_ Replace deleteIfExists with dropIfExists across models, improve keyword field handling
- _(tests)_ Remove outdated relationship tests and add new Geospatial tests
- _(models)_ Reorganize model namespaces, update schemas, and adjust tests
- _(elasticsearch)_ Remove Elasticsearch-related traits and tests that are not used anymore
- _(database)_ Remove legacy migrations and models
- _(composer)_ Streamline autoload-dev configuration
- _(Elasticsearch)_ Remove unused utilities and metadata handling
- _(core)_ Streamline Elasticsearch integration and enhance query processing
- _(query)_ Streamline aggregation methods and enhance metric support
- _(core)_ Remove redundant classes and streamline query options
- _(docs)_ Remove redundant ModelDocs.php
- _(core)_ Streamline code by removing unused components

### 🧪 Testing

- _(models)_ Update and streamline user and item model testing
- _(query-builder)_ Enhance where conditions with additional query capabilities

### Refract

- _(query-builder)_ Sort code

## \[4.5.0\] - 2024-10-17

### 🚀 Features

- _(connection)_ Add configurable insert chunk size and unsafe query option
- _(connection)_ Add configurable insert chunk size and unsafe query option

### 🐛 Bug Fixes

- _(Connection)_ Set default index_prefix to empty string
- _(exceptions)_ Add custom LogicException and improve method existence check

### 🚜 Refactor

- _(Bridge)_ Update client property to allow nullability in DSL Bridge
- _(connection)_ Remove redundant connection rebuild logic

### ⚙️ Miscellaneous Tasks

- _(dependencies)_ Update multiple PHP dependencies in composer.lock

## \[4.4.0\] - 2024-10-02

### 🚀 Features

- _(connection)_ Enhance Elasticsearch connection handling and validation
- _(connection)_ Add client info retrieval and improved error handling
- _(connection)_ Add client info retrieval and improved error handling
- _(logging)_ Enable configurable logging for Elasticsearch connections

### 🐛 Bug Fixes

- _(connection)_ Streamline setOptions and add auth_type validation

## \[4.3.0\] - 2024-09-23

### 🚀 Features

- _(elasticsearch)_ Enhance raw search functionality with ElasticCollection
- _(models)_ Introduce HiddenAnimal model with hidden properties
- _(models)_ Add Elasticsearch integration and enhance User and company models
- _(tests)_ Add multiple aggregations test for products

### 🐛 Bug Fixes

- _(exceptions)_ Remove unnecessary render method in MissingOrderException
- _(tests)_ Complete pending product ordering tests
- _(tests)_ Ensure ES connection for schema operations and optimize reindexing flow
- _(factory)_ Refine status generation in CompanyProfileFactory

### 🚜 Refactor

- _(config)_ Update PHPUnit config to correct

### 📚 Documentation

- _(README)_ Add test coverage badge.

### 🧪 Testing

- _(connection)_ Add unit tests for Elasticsearch DB connection
- _(schema)_ Add comprehensive tests for schema management and analyzers
- _(relationships)_ Add comprehensive tests for company and user relationships
- _(tests)_ Enhance bulk insert test coverage for Product model
- _(Schema)_ Enhance error detail assertions in ReindexTest
- _(meta)_ Add comprehensive unit tests for product metadata
- _(pagination)_ Enhance paginator test cases with additional assertions
- _(aggregation)_ Add comprehensive aggregation tests for Products
- _(search)_ Add comprehensive search tests for product model
- _(blogposts)_ Enhance post order test with dynamic sequences
- _(products)_ Remove outdated saveWithoutRefresh test case
- _(search)_ Refine product search tests for better accuracy
- _(AggregationTest)_ Add groupBy and distinct aggregate tests for UserLog
- _(products)_ Add test for color regex search with 'or' condition
- _(product-queries)_ Add comprehensive tests for complex query conditions
- _(models)_ Update 'Soft Delete' and 'Meta' tests for precision

### ⚙️ Miscellaneous Tasks

- _(workflows)_ Enhance test matrix and cache dependencies
- _(workflows)_ Enhance test matrix and cache dependencies
- _(dependencies)_ Add 'psr/http-factory' to project dependencies
- _(actions)_ Continue debugging actions.
- _(workflows)_ Update test workflows and create coverage workflow
- _(test-config)_ Enhance PHPUnit configuration for stricter testing
- _(test)_ Improve coverage reporting and configuration
- _(tests)_ Enhance XDebug coverage handling in CI script

## \[4.2.0\] - 2024-09-16

### 🐛 Bug Fixes

- _(DSL)_ Improve keyword mapping handling for nested fields

## \[4.1.0\] - 2024-09-04

### 🚀 Features

- _(static-pages)_ Add StaticPage model, factory, and migration
- _(ds/bridge, query/builder)_ Add bulk processing support
- _(core)_ Add bulk method to Connection, debug bulk operation in Builder
- _(models)_ Add new `Soft` and `Guarded` models, implement product sorting and tests
- _(elastic)_ Add optional refresh parameter to insertBulk

### 🐛 Bug Fixes

- _(pagination)_ Mark placeholder logic with FIXME comment in SearchAfterPaginator
- _(query, tests, dsl)_ Streamline bulk processing, fix tests, and correct response handling

### 🚜 Refactor

- _(tests)_ Remove redundant code and enhance insertion logic in SearchAfterPaginationTest
- _(build)_ Update code formatting and linting tools configuration

### 🎨 Styling

- _(docs)_ Update type hint in cursorPaginate method signature

### ⚙️ Miscellaneous Tasks

- _(ci)_ Enable PHPStan for code linting in CI configuration

## \[4.0.4\] - 2024-08-17

### 🚀 Features

- _(flakes)_ Add flake.nix for project setup with PHP, JS, and services integration
- _(flakes)_ Add new testbench commands for a and artisan execution
- _(database)_ Add product factory, migration, seeder, and model for elasticsearch
- _(models)_ Add new Eloquent models and their corresponding factories for User, UserProfile, Client, ClientLog, ClientProfile, BlogPost, Company, and CompanyLog
- _(migrations)_ Add users and photos tables creation
- _(dependencies)_ Add geo-math-php and pest packages
- _(models)_ Add PageHit model and factory
- _(routes, models)_ Add route configurations and PageHit model with corresponding factory
- _(models)_ Add soft deletes to Product model
- _(blog_posts)_ Add blog posts table migration and nested tests
- _(elasticsearch)_ Integrate Elasticsearch model and add dynamic indices tests
- _(relationships)_ Add polymorphic and one-to-many relationships with corresponding factories and tests
- _(ci)_ Add coverage execution to flake.nix for pest tests
- _(pagination)_ Add search_after pagination for Elasticsearch
- _(pagination)_ Add search_after pagination for Elasticsearch

### 🐛 Bug Fixes

- _(build)_ Pass arguments to testbench command

### 🚜 Refactor

- _(config)_ Add comments for PHP setup, Elasticsearch service, and git-cliff usage
- _(tests)_ Relocate and enhance ArchitectureTest for debugging prevention
- _(tests)_ Clean up TestCase by removing unused code and imports
- _(seeders)_ Rename ProductsSeeder to DatabaseSeeder
- _(seeders)_ Rename ProductsSeeder to DatabaseSeeder
- _(tests/migrations)_ Move schema setup to migration, update tests for consistency
- _(pagination)_ Rename and implement `searchAfter` for paginated queries

### 📚 Documentation

- Add CONTRIBUTING.md with setup and development instructions

### 🧪 Testing

- _(add)_ Integrate Pest and PHPUnit configuration
- _(architecture)_ Add safety tests to prevent usage of debug functions
- _(environment)_ Add unit test to confirm environment is set to testing
- _(database)_ Add unit tests for Product model
- Add base TestCase for Elasticsearch integration tests
- _(tests)_ Update TestCase to use new seeding approach and remove old migrations
- _(config)_ Consolidate testsuites and update environment variables in phpunit.xml
- _(schema)_ Add reindex test for products and holding_products
- _(aggregation)_ Add tests for product status and grouping
- _(Eloquent)_ Add deletion tests
- _(Eloquent)_ Add order and pagination tests
- _(Eloquent)_ Add querying tests
- _(Eloquent)_ Add tests for various save and update scenarios
- _(query)_ Add exception handling for product not found
- _(ChunkingTest)_ Add tests for processing large datasets using chunking methods
- _(elasticsearch)_ Add unit tests for es specific queries and filters
- _(eloquent)_ Add full-text search tests for Product model
- _(schema)_ Add tests for Elasticsearch schema management
- _(schema)_ Add tests for index field types

### ⚙️ Miscellaneous Tasks

- _(env)_ Add .envrc file with flake usage configuration
- _(git)_ Add .gitignore file to exclude environment and configuration files
- Add PHP CS Fixer configuration file for Laravel Pint standards
- _(build)_ Add justfile for task management
- _(gitignore)_ Add vendor directory to .gitignore
- _(composer)_ Update autoload paths, scripts, and config plugins in composer.json
- _(nix)_ Enable php-cs-fixer with Laravel standards
- _(database)_ Remove obsolete migration and related tests
- _(config)_ Remove obsolete ES_INDEX_PREFIX from phpunit.xml
- _(git)_ Update .gitignore to exclude PHPUnit cache files
- _(workflows)_ Add GitHub Actions workflow for CI
- _(workflows)_ Add manual trigger with branch input in CI configuration

<!-- generated by git-cliff -->

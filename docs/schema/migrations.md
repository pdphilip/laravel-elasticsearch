# Migrations

Given that there is little to no overlap with how Elasticsearch handles index management compared to SQL schema operations, the schema functionality for this package has been built from the ground up. This ensures that you have a tailored and effective toolset for working with Elasticsearch indices, designed to cater to their unique requirements and capabilities.

## Migration Class Creation

To use migrations for index management, you can create a normal migration class in Laravel as normal. However, it's crucial to note that the `up()` and `down()` methods encapsulate the specific Elasticsearch-related operations, namely:

- Schema Management: Utilizes the `PDPhilip\Elasticsearch\Schema\Schema` class.
- Index Definition: Leverages the `PDPhilip\Elasticsearch\Schema\IndexBlueprint` class for defining index structures.
- Analyzer Configuration: Employs the `PDPhilip\Elasticsearch\Schema\AnalyzerBlueprint` class for specifying custom analyzers.

## Full example

```php
<?php

use Illuminate\Database\Migrations\Migration;
use PDPhilip\Elasticsearch\Schema\Schema;
use PDPhilip\Elasticsearch\Schema\IndexBlueprint;
use PDPhilip\Elasticsearch\Schema\AnalyzerBlueprint;

class MyIndexes extends Migration
{
    public function up()
    {
        Schema::create('contacts', function (IndexBlueprint $index) {
          //first_name & last_name is automatically added to this field,
          //you can search by full_name without ever writing to full_name
          $index->text('first_name')->copyTo('full_name');
          $index->text('last_name')->copyTo('full_name');
          $index->text('full_name');

          //Multiple types => Order matters ::
          //Top level `email` will be a searchable text field
          //Sub Property will be a keyword type which can be sorted using orderBy('email.keyword')
          $index->text('email');
          $index->keyword('email');

          //Dates have an optional formatting as second parameter
          $index->date('first_contact', 'epoch_second');

          //Objects are defined with dot notation:
          $index->text('products.name');
          $index->float('products.price')->coerce(false);

          //Disk space considerations ::
          //Not indexed and not searchable:
          $index->keyword('internal_notes')->docValues(false);
          //Remove scoring for search:
          $index->array('tags')->norms(false);
          //Remove from index, can't search by this field but can still use for aggregations:
          $index->integer('score')->index(false);

          //If null is passed as value, then it will be saved as 'NA' which is searchable
          $index->keyword('favorite_color')->nullValue('NA');

          //Numeric Types
          $index->integer('some_int');
          $index->float('some_float');
          $index->double('some_double');
          $index->long('some_long');
          $index->short('some_short');
          $index->byte('some_byte');
          $index->halfFloat('some_half_float');
          $index->scaledFloat('some_scaled_float',140);
          $index->unsignedLong('some_unsigned_long');

          //Alias Example
          $index->text('notes');
          $index->alias('comments', 'notes');

          $index->geo('last_login');
          $index->date('created_at');
          $index->date('updated_at');

          //Settings
          $index->settings('number_of_shards', 3);
          $index->settings('number_of_replicas', 2);

          //Other Mappings
          $index->map('dynamic', false);
          $index->map('date_detection', false);

          //Custom Mapping
          $index->mapProperty('purchase_history', 'flattened');
        });

        //Example analyzer builder
        Schema::setAnalyser('contacts', function (AnalyzerBlueprint $settings) {
            $settings->analyzer('my_custom_analyzer')
                ->type('custom')
                ->tokenizer('punctuation')
                ->filter(['lowercase', 'english_stop'])
                ->charFilter(['emoticons']);
            $settings->tokenizer('punctuation')
                ->type('pattern')
                ->pattern('[ .,!?]');
            $settings->charFilter('emoticons')
                ->type('mapping')
                ->mappings([":) => _happy_", ":( => _sad_"]);
            $settings->filter('english_stop')
                ->type('stop')
                ->stopwords('_english_');
        });
    }

    public function down()
    {
        Schema::deleteIfExists('contacts');
    }
}
```

::: tip If the built-in helpers for mapping fields do not meet your needs, you can use the:

`$index->field($type, $field, $params)` method to define custom mappings.
:::

Example:

```php
$index->field('date', 'last_seen', [
    'format' => 'epoch_second||yyyy-MM-dd HH:mm:ss||yyyy-MM-dd',
    'ignore_malformed' => true,
]);
```

## Index Creation

### Schema::create

Creates a new index with the specified structure and settings.

```php
Schema::create('my_index', function (IndexBlueprint $index) {
    // Define fields, settings, and mappings
});
```

### Schema::createIfNotExists

Creates a new index only if it does not already exist.

```php
Schema::createIfNotExists('my_index', function (IndexBlueprint $index) {
    // Define fields, settings, and mappings
});
```

## Index Deletion

### Schema::delete

Deletes the specified index. Will throw an exception if the index does not exist.

```php
// Boolean
Schema::delete('my_index');
```

### Schema::deleteIfExists

Deletes the specified index if it exists.

```php
// Boolean
Schema::deleteIfExists('my_index');
```

## Index Modification

### Schema::modify

Modifies an existing index. This could involve adding new fields, changing analyzers, or updating other index settings.

```php
Schema::modify('my_index', function (IndexBlueprint $index) {
    // Modify fields, settings, and mappings
});
```

## Analyzer Configuration

### Schema::setAnalyser

Configures or updates analyzers for a specific index. This is crucial for defining how text fields are tokenized and analyzed.

```php
Schema::setAnalyser('my_index', function (AnalyzerBlueprint $settings) {
    // Define custom analyzers, tokenizers, and filters
});
```

## Index Lookup and Information Retrieval

### Schema::getIndex

Retrieves detailed information about a specific index or indices matching a pattern.

```php
Schema::getIndex('my_index');
Schema::getIndex('page_hits_*');
```

### Schema::getIndices

Equivalent to `Schema::getIndex('*')`, retrieves information about all indices on the Elasticsearch cluster.

```php
Schema::getIndices();
```

### Schema::getMappings

Retrieves the mappings for a specified index.

```php
Schema::getMappings('my_index');
```

### Schema::getSettings

Retrieves the settings for a specified index.

```php
Schema::getSettings('my_index');
```

### Schema::hasField

Checks if a specific field exists in the index's mappings.

```php
// Boolean
Schema::hasField('my_index', 'my_field');
```

### Schema::hasFields

Checks if multiple fields exist in the index's mappings.

```php
// Boolean
Schema::hasFields('my_index', ['field1', 'field2']);
```

### Schema::hasIndex

Checks if a specific index exists.

```php
// Boolean
Schema::hasIndex('my_index');
```

## Prefix Management

### Schema::overridePrefix

Temporarily overrides the default index prefix for subsequent operations. This can be useful in multi-tenant applications or when accessing indices across different environments.

```php
Schema::overridePrefix('some_other_prefix')->getIndex('my_index');
```

## Direct DSL Access

### Schema::dsl

Provides direct access to the Elasticsearch DSL, allowing for custom queries and operations not covered by other methods.

```php
Schema::dsl('close', ['index' => 'my_index']);
```

<?php

declare(strict_types=1);

use PDPhilip\Elasticsearch\Schema\AnalyzerBlueprint;
use PDPhilip\Elasticsearch\Schema\IndexBlueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

test('create and modify schemas', function () {

    // should clear any existing indices
    Schema::connection('elasticsearch')->deleteIfExists('contacts');

    // should create an index
    $contacts = Schema::connection('elasticsearch')->create('contacts', function (IndexBlueprint $index) {
        // first_name & last_name is automatically added to this field,
        // you can search by full_name without ever writing to full_name
        $index->text('first_name')->copyTo('full_name');
        $index->text('last_name')->copyTo('full_name');
        $index->text('full_name');

        // Multiple types => Order matters ::
        // Top level `email` will be a searchable text field
        // Sub Property will be a keyword type which can be sorted using orderBy('email.keyword')
        $index->text('email');
        $index->keyword('email');

        // Dates have an optional formatting as second parameter
        $index->date('first_contact', 'epoch_second');
        $index->ip('user_ip');
        // Objects are defined with dot notation:
        $index->text('products.name');
        $index->float('products.price')->coerce(false);

        // Disk space considerations ::
        // Not indexed and not searchable:
        $index->keyword('internal_notes')->docValues(false);
        // Remove scoring for search:
        $index->array('tags')->norms(false);
        // Remove from index, can't search by this field but can still use for aggregations:
        $index->integer('score')->index(false);

        // If null is passed as value, then it will be saved as 'NA' which is searchable
        $index->keyword('favorite_color')->nullValue('NA');

        $index->nested('meta', [
            'properties' => [
                'model' => [
                    'type' => 'keyword',
                ],
                'question' => [
                    'type' => 'keyword',
                ],
                'answer' => [
                    'type' => 'text',
                ],
            ],
        ]
        );

        // Alias Example
        $index->text('notes');
        $index->alias('comments', 'notes');

        $index->geo('last_login');
        $index->date('created_at');
        $index->date('updated_at');

        // Settings
        $index->settings('number_of_shards', 3);
        $index->settings('number_of_replicas', 2);

        // Other Mappings
        $index->map('dynamic', false);
        $index->map('date_detection', false);

        // Custom Mapping
        $index->mapProperty('purchase_history', 'flattened');
    });

    $this->assertTrue(! empty($contacts['contacts']['mappings']));
    $this->assertTrue(! empty($contacts['contacts']['settings']));
    $this->assertTrue($contacts['contacts']['mappings']['properties']['meta']['properties']['model']['type'] == 'keyword');

    // should set an analyser
    $contacts = Schema::connection('elasticsearch')->setAnalyser('contacts', function (AnalyzerBlueprint $settings) {
        $settings->analyzer('my_custom_analyzer')
            ->type('custom')
            ->tokenizer('punctuation')
            ->filter([
                'lowercase',
                'english_stop',
            ])
            ->charFilter(['emoticons']);
        $settings->tokenizer('punctuation')
            ->type('pattern')
            ->pattern('[ .,!?]');
        $settings->charFilter('emoticons')
            ->type('mapping')
            ->mappings([
                ':) => _happy_',
                ':( => _sad_',
            ]);
        $settings->filter('english_stop')
            ->type('stop')
            ->stopwords('_english_');
    });
    $this->assertTrue(! empty($contacts['contacts']['settings']['index']['analysis']['analyzer']['my_custom_analyzer']));

    // should return mappings
    $contacts = Schema::connection('elasticsearch')->getMappings('contacts');
    $this->assertTrue(! empty($contacts['contacts']['mappings']));

    // should return settings

    $contacts = Schema::connection('elasticsearch')->getSettings('contacts');
    $this->assertTrue(! empty($contacts['contacts']['settings']));

    // should not be able to create an index that already exists
    try {
        Schema::connection('elasticsearch')->create('contacts', function (IndexBlueprint $index) {
            $index->text('x_name');
            $index->mapProperty('purchase_history_x', 'flattened');
        });
        $this->assertTrue(false);
    } catch (Exception $e) {
        $this->assertTrue(true);
    }

    // should be able to modify an index
    $contacts = Schema::connection('elasticsearch')->modify('contacts', function (IndexBlueprint $index) {
        $index->text('my_favorite_color');
    });
    $this->assertTrue(! empty($contacts['contacts']['mappings']['properties']['my_favorite_color']));

    // should find the index and certain fields
    $hasIndex = Schema::hasIndex('contacts');
    $this->assertTrue($hasIndex);
    $hasIndex = Schema::hasIndex('contactz');
    $this->assertFalse($hasIndex);
    $hasField = Schema::hasField('contacts', 'my_favorite_color');
    $this->assertTrue($hasField);
    $hasField = Schema::hasField('contacts', 'my_favorite_colorzzz');
    $this->assertFalse($hasField);
    $hasFields = Schema::hasFields('contacts', [
        'my_favorite_color',
        'full_name',
        'internal_notes',
    ]);
    $this->assertTrue($hasFields);
    $hasFields = Schema::hasFields('contacts', [
        'my_favorite_color',
        'full_name',
        'internal_notes',
        'xxxx',
    ]);
    $this->assertFalse($hasFields);

    // should not be able to delete an index that does not exist
    $deleted = Schema::deleteIfExists('contactz');
    $this->assertFalse($deleted);
    try {
        Schema::delete('contactxxxz');
        $this->assertTrue(false);
    } catch (Exception $e) {
        $this->assertTrue(true);
    }

    // should clean up contacts index

    $deleted = Schema::deleteIfExists('contacts');
    $this->assertTrue($deleted);
    $this->assertFalse(Schema::hasIndex('contacts'));
});

it('should create an index will all numeric type mappings', function () {
    Schema::deleteIfExists('nums_lfg');
    Schema::create('nums_lfg', function (IndexBlueprint $index) {
        $index->long('lfg_long');
        $index->integer('lfg_int');
        $index->short('lfg_short');
        $index->byte('lfg_byte');
        $index->double('lfg_double');
        $index->float('lfg_float');
        $index->halfFloat('lfg_half_float');
        $index->scaledFloat('lfg_scaled_float', 140);
    });

    $mappings = Schema::getMappings('nums_lfg');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_long']['type'] == 'long');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_int']['type'] == 'integer');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_short']['type'] == 'short');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_byte']['type'] == 'byte');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_double']['type'] == 'double');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_float']['type'] == 'float');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_half_float']['type'] == 'half_float');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_scaled_float']['type'] == 'scaled_float');
    $this->assertTrue($mappings['nums_lfg']['mappings']['properties']['lfg_scaled_float']['scaling_factor'] == 140);
    // clean up
    Schema::deleteIfExists('nums_lfg');
});

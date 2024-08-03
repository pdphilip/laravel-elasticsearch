<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use PDPhilip\Elasticsearch\Schema\AnalyzerBlueprint;
use PDPhilip\Elasticsearch\Schema\IndexBlueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::deleteIfExists('test_index');
});

it('creates a new index', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->text('name');
        $index->integer('age');
        $index->settings('number_of_shards', 1);
        $index->settings('number_of_replicas', 1);
    });
    $exists = Schema::hasIndex('test_index');
    expect($exists)->toBeTrue();
});

it('deletes an index if it exists', function () {
    Schema::createIfNotExists('test_index', function (IndexBlueprint $index) {
        $index->text('description');
    });
    $deleted = Schema::deleteIfExists('test_index');
    expect($deleted)->toBeTrue();
    $exists = Schema::hasIndex('test_index');
    expect($exists)->toBeFalse();
});

it('modifies an existing index by adding a new field', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->text('title');
    });
    Schema::modify('test_index', function (IndexBlueprint $index) {
        $index->integer('year');
    });
    $hasField = Schema::hasField('test_index', 'year');
    expect($hasField)->toBeTrue();
});

it('sets a custom analyzer on an index', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->text('content');
    });
    Schema::setAnalyser('test_index', function (AnalyzerBlueprint $settings) {
        $settings->analyzer('custom_analyzer')
            ->type('custom')
            ->tokenizer('standard')
            ->filter(['lowercase', 'asciifolding']);
    });
    sleep(1);
    $settings = Schema::getSettings('test_index');
    expect($settings['test_index']['settings']['index']['analysis']['analyzer']['custom_analyzer'])->toBeArray();
});

it('retrieves mappings of an index', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->text('info');
        $index->keyword('tag');
    });
    $mappings = Schema::getMappings('test_index');
    expect($mappings['test_index']['mappings']['properties']['info']['type'])->toEqual('text')
        ->and($mappings['test_index']['mappings']['properties']['tag']['type'])->toEqual('keyword');
});

it('checks if an index has specific fields', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->text('name');
        $index->integer('age');
    });
    $hasFields = Schema::hasFields('test_index', ['name', 'age']);
    expect($hasFields)->toBeTrue();
});

it('fails to delete a non-existent index', function () {
    $deleted = Schema::deleteIfExists('nonexistent_index');
    expect($deleted)->toBeFalse();
});

it('overrides index prefix for operations', function () {
    Schema::overridePrefix('test_prefix');
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->text('message');
    });
    $exists = Schema::hasIndex('test_prefix_test_index');
    expect($exists)->toBeTrue();
    Schema::deleteIfExists('test_prefix_test_index');
});

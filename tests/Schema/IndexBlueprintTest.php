<?php

declare(strict_types=1);

namespace Tests\Feature;

use PDPhilip\Elasticsearch\Schema\IndexBlueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

beforeEach(function () {
    Schema::deleteIfExists('test_index');
});

it('validates text field type', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->text('info');
    });
    $mappings = Schema::getMappings('test_index');
    expect($mappings['test_index']['mappings']['properties']['info']['type'])->toEqual('text');
});

it('validates keyword field type', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->keyword('tag');
    });
    $mappings = Schema::getMappings('test_index');
    expect($mappings['test_index']['mappings']['properties']['tag']['type'])->toEqual('keyword');
});

it('validates integer field type', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->integer('age');
    });
    $mappings = Schema::getMappings('test_index');
    expect($mappings['test_index']['mappings']['properties']['age']['type'])->toEqual('integer');
});

it('validates float field type', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->float('price');
    });
    $mappings = Schema::getMappings('test_index');
    expect($mappings['test_index']['mappings']['properties']['price']['type'])->toEqual('float');
});

it('validates date field type', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->date('birthdate');
    });
    $mappings = Schema::getMappings('test_index');
    expect($mappings['test_index']['mappings']['properties']['birthdate']['type'])->toEqual('date');
});

it('validates boolean field type', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->boolean('is_active');
    });
    $mappings = Schema::getMappings('test_index');
    expect($mappings['test_index']['mappings']['properties']['is_active']['type'])->toEqual('boolean');
});

it('validates geo_point field type', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->geo('location');
    });
    $mappings = Schema::getMappings('test_index');
    expect($mappings['test_index']['mappings']['properties']['location']['type'])->toEqual('geo_point');
});

it('validates ip field type', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->ip('user_ip');
    });
    $mappings = Schema::getMappings('test_index');
    expect($mappings['test_index']['mappings']['properties']['user_ip']['type'])->toEqual('ip');
});

it('validates nested field type', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->nested('user', [
            'properties' => [
                'name' => [
                    'type' => 'text',
                ],
                'age' => [
                    'type' => 'integer',
                ],
            ],
        ]);
    });
    $mappings = Schema::getMappings('test_index');
    expect($mappings['test_index']['mappings']['properties']['user']['type'])->toEqual('nested')
        ->and($mappings['test_index']['mappings']['properties']['user']['properties']['name']['type'])->toEqual('text')
        ->and($mappings['test_index']['mappings']['properties']['user']['properties']['age']['type'])->toEqual('integer');
});

it('validates object field type with dot notation', function () {
    Schema::create('test_index', function (IndexBlueprint $index) {
        $index->text('user.name');
        $index->integer('user.age');
    });
    $mappings = Schema::getMappings('test_index');
    expect($mappings['test_index']['mappings']['properties']['user']['properties']['name']['type'])->toEqual('text')
        ->and($mappings['test_index']['mappings']['properties']['user']['properties']['age']['type'])->toEqual('integer');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Schema\Builder as SchemaBuilder;

test('Connection', function () {
    $connection = DB::connection('elasticsearch');

    expect($connection)->toBeInstanceOf(Connection::class)
        ->and($connection->getDriverName())->toEqual('elasticsearch')
        ->and($connection->getDriverTitle())->toEqual('elasticsearch');
});

test('Reconnect', function () {
    $c1 = DB::connection('elasticsearch');
    $c2 = DB::connection('elasticsearch');
    expect(spl_object_hash($c1) === spl_object_hash($c2))->toBeTrue();

    $c1 = DB::connection('elasticsearch');
    DB::purge('elasticsearch');
    $c2 = DB::connection('elasticsearch');
    expect(spl_object_hash($c1) !== spl_object_hash($c2))->toBeTrue();
});

test('Schema Builder', function () {
    $schema = DB::connection('elasticsearch')->getSchemaBuilder();
    expect($schema)->toBeInstanceOf(SchemaBuilder::class);
});

test('Driver Name', function () {
    $driver = DB::connection('elasticsearch')->getDriverName();
    expect($driver === 'elasticsearch')->toBeTrue();
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\ElasticClient as Client;
use PDPhilip\Elasticsearch\Helpers\Helpers;
use PDPhilip\Elasticsearch\Schema\Builder as SchemaBuilder;

function getLaravelVersion(): int
{
    try {
        return Helpers::getLaravelCompatabilityVersion();
    } catch (Exception $e) {
        return 0;
    }
}

test('Laravel Compatability for v'.getLaravelVersion().' loaded', function () {
    expect(getLaravelVersion())->toBeGreaterThan(10)
        ->and(getLaravelVersion())->toBeLessThan(13);
});

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

test('Disconnect And Create New Connection', function () {
    $connection = DB::connection('elasticsearch');
    expect($connection)->toBeInstanceOf(Connection::class);
    $client = $connection->getClient();
    expect($client)->toBeInstanceOf(Client::class);

    $connection->disconnect();
    $client = $connection->getClient();
    expect($client)->toBeNull();
    DB::purge('elasticsearch');

    $connection = DB::connection('elasticsearch');
    expect($connection)->toBeInstanceOf(Connection::class);
    $client = $connection->getClient();
    expect($client)->toBeInstanceOf(Client::class);

});

test('DB', function () {
    $connection = DB::connection('elasticsearch');
    expect($connection->getClient())->toBeInstanceOf(Client::class);
});

test('Connection Without auth_type', function () {
    new Connection(['name' => 'test']);
})->throws(RuntimeException::class, 'Invalid [auth_type] in database config. Must be: http or cloud');

test('Cloud Connection Without cloud_id', function () {
    $this->expectException(RuntimeException::class);

    new Connection(['name' => 'test', 'auth_type' => 'cloud']);
})->throws(RuntimeException::class, 'auth_type of `cloud` requires `cloud_id` to be set');

test('Http Connection Without hosts', function () {
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('auth_type of `http` requires `hosts` to be set');

    new Connection(['name' => 'test', 'auth_type' => 'http']);
})->throws(RuntimeException::class, 'auth_type of `http` requires `hosts` to be set and be an array');

test('Prefix', function () {
    $config = [
        'name' => 'test',
        'auth_type' => 'http',
        'hosts' => ['http://localhost:9200'],
        'index_prefix' => 'prefix_',
    ];

    $connection = new Connection($config);

    expect($connection->getIndexPrefix())->toBe('prefix_');
});

test('Schema Builder', function () {
    $schema = DB::connection('elasticsearch')->getSchemaBuilder();
    expect($schema)->toBeInstanceOf(SchemaBuilder::class);
});

test('Driver Name', function () {
    $driver = DB::connection('elasticsearch')->getDriverName();
    expect($driver === 'elasticsearch')->toBeTrue();
});

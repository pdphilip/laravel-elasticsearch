<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PDPhilip\Elasticsearch\Connection;
use PDPhilip\Elasticsearch\Schema\Builder as SchemaBuilder;
use Elastic\Elasticsearch\Client;

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
  $this->assertNull($client);
  DB::purge('elasticsearch');

  $connection = DB::connection('elasticsearch');
  expect($connection)->toBeInstanceOf(Connection::class);
  $client = $connection->getClient();
  expect($client)->toBeInstanceOf(Client::class);

});

test('DB', function () {
  $connection = DB::connection('elasticsearch');
  $this->assertInstanceOf(Client::class, $connection->getClient());
});

test('Connection Without auth_type', function () {
  $this->expectException(RuntimeException::class);
  $this->expectExceptionMessage('Invalid [auth_type] in database config. Must be: http or cloud');

  new Connection(['name' => 'test']);
});

test('Cloud Connection Without cloud_id', function () {
  $this->expectException(RuntimeException::class);
  $this->expectExceptionMessage('auth_type of `cloud` requires `cloud_id` to be set');

  new Connection(['name' => 'test', 'auth_type' => 'cloud']);
});

test('Http Connection Without hosts', function () {
  $this->expectException(RuntimeException::class);
  $this->expectExceptionMessage('auth_type of `http` requires `hosts` to be set');

  new Connection(['name' => 'test', 'auth_type' => 'http']);
});

test('Schema Builder', function () {
    $schema = DB::connection('elasticsearch')->getSchemaBuilder();
    expect($schema)->toBeInstanceOf(SchemaBuilder::class);
});

test('Driver Name', function () {
    $driver = DB::connection('elasticsearch')->getDriverName();
    expect($driver === 'elasticsearch')->toBeTrue();
});

<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests;

use OmniTerm\OmniTermServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use PDPhilip\Elasticsearch\ElasticServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ElasticServiceProvider::class,
            OmniTermServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'elasticsearch');

        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ]);

        $app['config']->set('database.connections.elasticsearch', [
            'driver' => 'elasticsearch',
            'auth_type' => 'http',
            'hosts' => ['http://localhost:9200'],
            'options' => [
                'logging' => true,
            ],
        ]);
        $app['config']->set('database.connections.elasticsearch_with_default_limit', [
            'driver' => 'elasticsearch',
            'auth_type' => 'http',
            'hosts' => ['http://localhost:9200'],
            'options' => [
                'default_limit' => 4,
                'logging' => true,
            ],
        ]);

        $app['config']->set('database.connections.elasticsearch_unsafe', [
            'driver' => 'elasticsearch',
            'auth_type' => 'http',
            'hosts' => ['http://localhost:9200'],
            'options' => [
                'bypass_map_validation' => true,
                'insert_chunk_size' => 10000,
                'logging' => true,
            ],
        ]);

        $app['config']->set('database.connections.elasticsearch_with_default_track_total_hits', [
            'driver' => 'elasticsearch',
            'auth_type' => 'http',
            'hosts' => ['http://localhost:9200'],
            'options' => [
                'track_total_hits' => true,
                'logging' => true,
            ],
        ]);
    }
}

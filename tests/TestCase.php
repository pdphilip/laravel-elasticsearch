<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests;

use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use PDPhilip\Elasticsearch\ElasticServiceProvider;
use Workbench\Database\Seeders\DatabaseSeeder;

use function Orchestra\Testbench\workbench_path;

class TestCase extends Orchestra
{
    use WithWorkbench;

    protected function getPackageProviders($app): array
    {
        return [
            ElasticServiceProvider::class,
        ];
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        // Testing migrations are located in workbench database/migrations
        $this->loadMigrationsFrom(
            workbench_path('database/migrations')
        );
    }

    protected function defineDatabaseSeeders(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.elasticsearch', [
            'driver' => 'elasticsearch',
            'auth_type' => 'http',
            'hosts' => ['http://localhost:9200'],
            'options' => [
              'logging' => true
            ]
        ]);
    }
}

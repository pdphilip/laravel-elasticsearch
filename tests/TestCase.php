<?php

  declare(strict_types=1);

  namespace PDPhilip\Elasticsearch\Tests;

  use Orchestra\Testbench\TestCase as Orchestra;
  use PDPhilip\Elasticsearch\ElasticServiceProvider;

  use function Orchestra\Testbench\workbench_path;
  use function Orchestra\Testbench\artisan;

  class TestCase extends Orchestra
  {
    protected function getPackageProviders($app): array
    {
      return [
        ElasticServiceProvider::class,
      ];
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations(): void
    {

      # Testing migrations are located in workbench database/migrations
      $this->loadMigrationsFrom(
        workbench_path('database/migrations')
      );

      # When we set up the app we migrate elasticsearch
      artisan($this, 'migrate:fresh', ['--seed', '--database' => 'elasticsearch']);


      # Tearing down the app should roll back the migrations
      $this->beforeApplicationDestroyed(
        fn () => artisan($this, 'migrate:rollback', ['--database' => 'elasticsearch'])
      );

    }

    protected function getEnvironmentSetUp($app): void
    {
      $app['config']->set('database.default', 'testing');
      $app['config']->set('database.connections.elasticsearch', [
        'driver' => 'elasticsearch',
        'auth_type' => 'http',
        'hosts' => ['http://localhost:9200']
      ]);
    }


  }

<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch;

use Illuminate\Support\ServiceProvider;
use PDPhilip\Elasticsearch\Eloquent\Model;

class ElasticServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot(): void
    {
        Model::setConnectionResolver($this->app['db']);
        Model::setEventDispatcher($this->app['events']);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        require_once __DIR__.'/Laravel/compatibility-loader.php';
        // Add database driver.
        $this->app->resolving('db', function ($db) {
            $db->extend('elasticsearch', function ($config, $name) {
                $config['name'] = $name;

                return new Connection($config);
            });
        });

    }
}

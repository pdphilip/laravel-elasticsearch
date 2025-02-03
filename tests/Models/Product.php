<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

class Product extends Model
{
    protected $connection = 'elasticsearch';

    protected $table = 'products';

    protected static $unguarded = true;

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('elasticsearch');

        $schema->dropIfExists('products');
        $schema->create('products', function (Blueprint $table) {
            $table->date('created_at');
            $table->date('updated_at');
        });
    }
}

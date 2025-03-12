<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

class Address extends Model
{
    protected $connection = 'elasticsearch';

    protected $table = 'address';

    protected static $unguarded = true;

    public static function executeSchema()
    {
        $schema = Schema::connection('elasticsearch');

        $schema->dropIfExists('address');
        $schema->createIfNotExists('address', function (Blueprint $table) {
            $table->date('created_at');
            $table->date('updated_at');
        });
    }
}

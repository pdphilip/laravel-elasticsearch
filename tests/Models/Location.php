<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;
use PDPhilip\Elasticsearch\Tests\Concerns\TestsWithIdStrategies;

class Location extends Model
{
    use TestsWithIdStrategies;

    protected $keyType = 'string';

    protected $connection = 'elasticsearch';

    protected $table = 'locations';

    protected static $unguarded = true;

    public static function executeSchema()
    {
        $schema = Schema::connection('elasticsearch');

        $schema->dropIfExists('locations');
        $schema->create('locations', function (Blueprint $table) {
            $table->geoPoint('point');
            $table->geoShape('location');

            $table->date('created_at');
            $table->date('updated_at');
        });
    }
}

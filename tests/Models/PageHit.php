<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use PDPhilip\Elasticsearch\Eloquent\DynamicIndex;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

class PageHit extends Model
{
    use DynamicIndex;

    protected $connection = 'elasticsearch';

    protected $table = 'page_hits';

    protected static $unguarded = true;

    public static function executeSchema()
    {
        $schema = Schema::connection('elasticsearch');

        collect([
            '2021-01-01',
            '2021-01-02',
            '2021-01-03',
            '2021-01-04',
            '2021-01-05',
        ])->each(function (string $index) use ($schema) {
            Schema::dropIfExists('page_hits');

            Schema::dropIfExists('page_hits_'.$index);

            $schema->create('page_hits_'.$index, function (Blueprint $table) {
                $table->date('created_at');
                $table->date('updated_at');
            });

        });

    }
}

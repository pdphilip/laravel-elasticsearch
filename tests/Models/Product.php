<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;
use PDPhilip\Elasticsearch\Tests\Concerns\TestsWithIdStrategies;

class Product extends Model
{
    use TestsWithIdStrategies;

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
            $table->text('name', hasKeyword: true);
            $table->text('description', hasKeyword: true);
            $table->keyword('status');
            $table->keyword('category');
            $table->keyword('color');
            $table->keyword('sku');
            $table->integer('price');
            $table->integer('quantity');
            $table->geoPoint('location');
            $table->nested('variants')->properties(function (Blueprint $nested) {
                $nested->keyword('sku');
                $nested->keyword('color');
            });
            $table->date('created_at');
            $table->date('updated_at');
        });
    }

    public static function buildRecords($limit = 100)
    {
        $records = [];
        while ($limit) {
            $records[] = [
                'state' => rand(1, 100),
            ];
            $limit--;
        }
        Product::insert($records);
        //        Product::withoutRefresh()->insert($records);
    }
}

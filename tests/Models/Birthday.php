<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

class Birthday extends Model
{
    protected $connection = 'elasticsearch';

    protected $table = 'birthday';

    protected $fillable = ['name', 'birthday'];

    protected $casts = ['birthday' => 'datetime'];

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('elasticsearch');

        $schema->dropIfExists('birthday');
        $schema->create('birthday', function (Blueprint $table) {
            $table->date('birthday');
            $table->date('created_at');
            $table->date('updated_at');
        });
    }
}

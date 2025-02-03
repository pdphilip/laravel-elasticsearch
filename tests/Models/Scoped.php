<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use PDPhilip\Elasticsearch\Eloquent\Builder;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

class Scoped extends Model
{
    protected $connection = 'elasticsearch';

    protected $fillable = ['name', 'favorite'];

    protected $table = 'scoped';

    protected $casts = ['birthday' => 'datetime'];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('favorite', function (Builder $builder) {
            $builder->where('favorite', true);
        });
    }

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('elasticsearch');

        $schema->dropIfExists('scoped');
        $schema->create('scoped', function (Blueprint $table) {
            $table->date('birthday');
            $table->date('created_at');
            $table->date('updated_at');
        });
    }
}

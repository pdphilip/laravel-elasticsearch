<?php

namespace PDPhilip\Elasticsearch\Tests\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Eloquent\SoftDeletes;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

class Soft extends Model
{
    use MassPrunable;
    use SoftDeletes;

    protected $connection = 'elasticsearch';

    protected static $unguarded = true;

    protected $casts = ['deleted_at' => 'datetime'];

    public function prunable(): Builder
    {
        return $this->newQuery();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('elasticsearch');

        $schema->dropIfExists('softs');
        $schema->create('softs', function (Blueprint $table) {
            $table->date('deleted_at');
            $table->date('created_at');
            $table->date('updated_at');
        });
    }
}

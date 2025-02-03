<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

class Experience extends Model
{
    protected $connection = 'elasticsearch';

    protected $table = 'experiences';

    protected static $unguarded = true;

    protected $casts = ['years' => 'int'];

    public function sqlUsers()
    {
        return $this->morphToMany(SqlUser::class, 'experienced');
    }

    public static function executeSchema()
    {
        $schema = Schema::connection('elasticsearch');

        $schema->dropIfExists('experienceds');
        $schema->create('experienceds', function (Blueprint $table) {
            $table->date('created_at');
            $table->date('updated_at');
        });

        $schema->dropIfExists('experiences');
        $schema->create('experiences', function (Blueprint $table) {
            $table->string('name');
            $table->keyword('sql_user_id');
            $table->string('author');
            $table->date('created_at');
            $table->date('updated_at');
        });
    }
}

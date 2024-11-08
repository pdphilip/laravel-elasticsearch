<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

/**
 * @property string $title
 * @property string $author
 * @property array $chapters
 */
class Birthday extends Model
{
    protected $connection = 'elasticsearch';

    protected $index = 'birthday';

    protected $fillable = ['name', 'birthday'];

    protected $casts = ['birthday' => 'datetime'];

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('elasticsearch');

        $schema->deleteIfExists('birthday');
        $schema->create('birthday', function (Blueprint $table) {
            $table->date('birthday');
            $table->date('created_at');
            $table->date('updated_at');
        });
    }
}

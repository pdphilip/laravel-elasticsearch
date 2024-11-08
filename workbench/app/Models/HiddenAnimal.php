<?php

namespace Workbench\App\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

/**
 * @property string $name
 * @property string $country
 * @property bool $can_be_eaten
 */
final class HiddenAnimal extends Model
{
    protected $connection = 'elasticsearch';

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'country',
        'can_be_eaten',
    ];

    protected $hidden = ['country'];

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('elasticsearch');

        $schema->deleteIfExists('hidden_animals');
        $schema->create('hidden_animals', function (Blueprint $table) {

            $table->date('created_at');
            $table->date('updated_at');
        });
    }
}

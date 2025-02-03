<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

/**
 * @property string $title
 * @property string $author
 * @property array $chapters
 */
class Label extends Model
{
    protected $connection = 'elasticsearch';

    protected $index = 'labels';

    protected static $unguarded = true;

    protected $fillable = [
        'name',
        'author',
        'chapters',
    ];

    public function users()
    {
        return $this->morphedByMany(User::class, 'labelled');
    }

    public function sqlUsers(): MorphToMany
    {
        return $this->morphedByMany(SqlUser::class, 'labeled');
    }

    public function clients()
    {
        return $this->morphedByMany(Client::class, 'labelled');
    }

    public function clientsWithCustomKeys()
    {
        return $this->morphedByMany(
            Client::class,
            'clabelled',
            'clabelleds',
            'clabel_ids',
            'cclabelled_id',
            'clabel_id',
            'cclient_id',
        );
    }

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('elasticsearch');

        $schema->dropIfExists('labels');
        $schema->create('labels', function (Blueprint $table) {
            $table->string('name');
            $table->string('author');
            $table->keyword('labeleds.labeled_id');
            //            $table->string('labeleds')->fields(function (Blueprint $table) {
            //              $table->keyword('labeled_id');
            //            });
            $table->date('created_at');
            $table->date('updated_at');
        });
    }
}

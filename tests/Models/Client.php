<?php

namespace PDPhilip\Elasticsearch\Tests\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use PDPhilip\Elasticsearch\Eloquent\HybridRelations;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

class Client extends Model
{
    use HybridRelations;

    protected $connection = 'elasticsearch';

    protected $table = 'clients';

    protected static $unguarded = true;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function skillsWithCustomKeys()
    {
        return $this->belongsToMany(
            Skill::class,
            foreignPivotKey: 'cclient_ids',
            relatedPivotKey: 'cskill_ids',
            parentKey: 'cclient_id',
            relatedKey: 'cskill_id',
        );
    }

    public function photo(): MorphOne
    {
        return $this->morphOne(Photo::class, 'has_image');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'data.client_id', 'data.client_id');
    }

    public function labels()
    {
        return $this->morphToMany(Label::class, 'labelled');
    }

    public function labelsWithCustomKeys()
    {
        return $this->morphToMany(
            Label::class,
            'clabelled',
            'clabelleds',
            'cclabelled_id',
            'clabel_ids',
            'cclient_id',
            'clabel_id',
        );
    }

    public static function executeSchema()
    {
        $schema = Schema::connection('elasticsearch');

        $schema->dropIfExists('clients');
        $schema->create('clients', function (Blueprint $table) {
            $table->date('created_at');
            $table->date('updated_at');
        });

        $schema->dropIfExists('client_user');
        $schema->create('client_user', function (Blueprint $table) {
            $table->date('created_at');
            $table->date('updated_at');
        });
    }
}

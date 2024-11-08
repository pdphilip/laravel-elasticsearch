<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use PDPhilip\Elasticsearch\Eloquent\HybridRelations;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

/**
 * App\Models\Client
 *
 ******Fields*******
 *
 * @property string $_id
 * @property string $company_id
 * @property string $name
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 ******Relationships*******
 * @property-read Company $company
 * @property-read ClientLog $clientLogs
 * @property-read ClientProfile $clientProfile
 *
 ******Attributes*******
 * @property-read mixed $status_name
 * @property-read mixed $status_color
 *
 * @mixin \Eloquent
 */
class Client extends Model
{
    use HasFactory;
    use HybridRelations;

    protected $connection = 'elasticsearch';

    protected $table = 'clients';

    protected static $unguarded = true;

    //model definition =====================================
    public static $statuses = [

        1 => [
            'name' => 'New',
            'level' => 1,
            'color' => 'text-neutral-500',
            'time_model' => 'created_at',
        ],

    ];

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

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
        $schema = Schema::connection('elasticsearch');

        $schema->deleteIfExists('clients');
        $schema->create('clients', function (Blueprint $table) {
            $table->date('created_at');
            $table->date('updated_at');
        });

        $schema->deleteIfExists('client_user');
        $schema->create('client_user', function (Blueprint $table) {
            $table->date('created_at');
            $table->date('updated_at');
        });
    }
}

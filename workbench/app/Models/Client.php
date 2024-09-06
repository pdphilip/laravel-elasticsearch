<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use PDPhilip\Elasticsearch\Eloquent\HybridRelations;
use Workbench\Database\Factories\ClientFactory;

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

    //model definition =====================================
    public static $statuses = [

        1 => [
            'name' => 'New',
            'level' => 1,
            'color' => 'text-neutral-500',
            'time_model' => 'created_at',
        ],

    ];

    //Relationships  =====================================

    public function clientLogs()
    {
        return $this->hasMany(ClientLog::class);
    }

    public function clientProfile()
    {
        return $this->hasOne(ClientProfile::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }
}

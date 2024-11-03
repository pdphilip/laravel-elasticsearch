<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\HybridRelations;
use PDPhilip\Elasticsearch\Eloquent\Model;
use Workbench\Database\Factories\CompanyFactory;

class Company extends Model
{
    use HasFactory, HybridRelations;

    protected $connection = 'elasticsearch';

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

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function userLogs()
    {
        return $this->hasMany(UserLog::class);
    }

    public function companyLogs()
    {
        return $this->hasMany(CompanyLog::class);
    }

    public function companyProfile()
    {
        return $this->hasOne(CompanyProfile::class);
    }

    public function avatar()
    {
        return $this->morphOne(Avatar::class, 'imageable');
    }

    public function photos()
    {
        return $this->morphMany(Photo::class, 'photoable');
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
}

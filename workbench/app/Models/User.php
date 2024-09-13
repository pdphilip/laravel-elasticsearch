<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PDPhilip\Elasticsearch\Eloquent\HybridRelations;
use Workbench\Database\Factories\UserFactory;

class User extends Authenticatable
{
    use HasFactory, HybridRelations, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function userLogs()
    {
        return $this->hasMany(UserLog::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function userProfile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function avatar()
    {
        return $this->morphOne(Avatar::class, 'imageable');
    }

    public function photos()
    {
        return $this->morphMany(Photo::class, 'photoable');
    }

    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function getFirstNameAttribute($value)
    {
        return strtoupper($value);
    }

    public static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}

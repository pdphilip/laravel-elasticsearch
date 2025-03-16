<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model as Eloquent;
use Workbench\Database\Factories\UserProfileFactory;

/**
 * App\Models\UserProfile
 *
 ******Fields*******
 *
 * @property string $_id
 * @property string $user_id
 * @property string $twitter
 * @property string $facebook
 * @property string $address
 * @property string $timezone
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 ******Relationships*******
 * @property-read User $user
 *
 ******Attributes*******
 * @property-read mixed $status_name
 * @property-read mixed $status_color
 *
 * @mixin \Eloquent
 */
class UserProfile extends Eloquent
{
    use HasFactory;

    protected $connection = 'elasticsearch';

    // Relationships  =====================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function newFactory(): UserProfileFactory
    {
        return UserProfileFactory::new();
    }
}

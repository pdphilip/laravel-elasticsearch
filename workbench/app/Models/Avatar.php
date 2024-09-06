<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model;
use Workbench\Database\Factories\AvatarFactory;

/**
 * App\Models\Avatar
 *
 ******Fields*******
 *
 * @property string $_id
 * @property string $url
 * @property string $imageable_id
 * @property string $imageable_type
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
 * @mixin Model
 */
class Avatar extends Model
{
    use HasFactory;

    protected $connection = 'elasticsearch';

    //Relationships  =====================================

    public function imageable()
    {
        return $this->morphTo();
    }

    public static function newFactory(): AvatarFactory
    {
        return AvatarFactory::new();
    }
}

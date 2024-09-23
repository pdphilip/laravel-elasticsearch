<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model;
use Workbench\Database\Factories\EsPhotoFactory;

/**
 * App\Models\Photo
 *
 * @property int $id
 * @property string $url
 * @property string $photoable_id
 * @property string $photoable_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
class EsPhoto extends Model
{
    use HasFactory;

    protected $connection = 'elasticsearch';

    public function photoable()
    {
        return $this->morphTo();
    }

    public static function newFactory(): EsPhotoFactory
    {
        return EsPhotoFactory::new();
    }
}

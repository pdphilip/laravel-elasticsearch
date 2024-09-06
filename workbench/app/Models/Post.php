<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model;
use Workbench\Database\Factories\PostFactory;

/**
 * App\Models\Post
 *
 ******Fields*******
 *
 * @property string $_id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Post extends Model
{
    use HasFactory;

    protected $connection = 'elasticsearch';

    //    const MAX_SIZE = 5;

    protected $fillable = ['title', 'slug', 'content'];

    public static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }
}

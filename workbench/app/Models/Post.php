<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
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

    /**
     * Get all of the tags for the post.
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }
}

<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Relations\MorphToMany;
use Workbench\Database\Factories\TagFactory;

class Tag extends Model
{
    use HasFactory;

    protected $connection = 'elasticsearch';
    protected $fillable = ['name'];

  /**
   * Get all of the posts that are assigned this tag.
   */
  public function posts(): MorphToMany
  {
    return $this->morphedByMany(Post::class, 'taggable');
  }

  /**
   * Get all of the videos that are assigned this tag.
   */
  public function videos(): MorphToMany
  {
    return $this->morphedByMany(Video::class, 'taggable');
  }

  public static function newFactory(): TagFactory
  {
    return TagFactory::new();
  }
}

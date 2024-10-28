<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Relations\MorphToMany;
use Workbench\Database\Factories\VideoFactory;

class Video extends Model
{
    use HasFactory;

    protected $connection = 'elasticsearch';

    //    const MAX_SIZE = 5;

    protected $fillable = ['name'];

  /**
   * Get all of the tags for the post.
   */
  public function tags(): MorphToMany
  {
    return $this->morphToMany(Tag::class, 'taggable');
  }

    public static function newFactory(): VideoFactory
    {
        return VideoFactory::new();
    }
}

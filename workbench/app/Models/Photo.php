<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model;
use Workbench\Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use PDPhilip\Elasticsearch\Schema\IndexBlueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

class Photo extends Model
{
    use HasFactory;

  protected $connection = 'elasticsearch';
  protected $table = 'photos';
  protected static $unguarded = true;

  public function hasImage(): MorphTo
  {
    return $this->morphTo();
  }

  public function hasImageWithCustomOwnerKey(): MorphTo
  {
    return $this->morphTo(ownerKey: 'cclient_id');
  }

  /**
   * Check if we need to run the schema.
   */
  public static function executeSchema()
  {
    $schema = Schema::connection('elasticsearch');

    $schema->deleteIfExists('photos');
    $schema->create('photos', function (IndexBlueprint $table) {
      $table->date('created_at');
      $table->date('updated_at');
    });
  }

}

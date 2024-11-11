<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Eloquent\SoftDeletes;
use Workbench\Database\Factories\SoftFactory;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

/** @property Carbon $deleted_at */
class Soft extends Model
{
    use SoftDeletes;
    use MassPrunable;
    use HasFactory;

    protected $connection = 'elasticsearch';

    protected static $unguarded = true;

    protected $casts = ['deleted_at' => 'datetime'];


  public static function newFactory(): SoftFactory
  {
    return SoftFactory::new();
  }

  public function prunable(): Builder
  {
    return $this->newQuery();
  }

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  /**
   * Check if we need to run the schema.
   */
  public static function executeSchema()
  {
    $schema = Schema::connection('elasticsearch');

    $schema->dropIfExists('softs');
    $schema->create('softs', function (Blueprint $table) {
      $table->date('deleted_at');
      $table->date('created_at');
      $table->date('updated_at');
    });
  }

}

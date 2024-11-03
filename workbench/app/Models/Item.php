<?php

  declare(strict_types=1);

  namespace Workbench\App\Models;

  use Carbon\Carbon;
  use PDPhilip\Elasticsearch\Eloquent\Builder;
  use PDPhilip\Elasticsearch\Eloquent\Model;
  use PDPhilip\Elasticsearch\Relations\BelongsTo;
  use PDPhilip\Elasticsearch\Schema\IndexBlueprint;
  use PDPhilip\Elasticsearch\Schema\Schema;

  /** @property Carbon $created_at */
  class Item extends Model
  {

    protected $connection = 'elasticsearch';
    protected $index = 'items';
    protected static $unguarded = true;

    public function user(): BelongsTo
    {
      return $this->belongsTo(User::class);
    }

    public function scopeSharp(Builder $query)
    {
      return $query->where('type', 'sharp');
    }

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
      $schema = Schema::connection('elasticsearch');

      $schema->deleteIfExists('items');
      $schema->create('items', function (IndexBlueprint $table) {

        $table->keyword('user_id');
        $table->text('user_id');

        $table->date('created_at');
        $table->date('updated_at');
      });
    }

  }

<?php

  declare(strict_types=1);

  namespace Workbench\App\Models;

  use Carbon\Carbon;
  use PDPhilip\Elasticsearch\Eloquent\Builder;
  use PDPhilip\Elasticsearch\Eloquent\Model;
  use PDPhilip\Elasticsearch\Relations\BelongsTo;

  /** @property Carbon $created_at */
  class Item extends Model
  {

    protected $connection = 'elasticsearch';
    protected $index = 'skills';
    protected static $unguarded = true;

    public function user(): BelongsTo
    {
      return $this->belongsTo(User::class);
    }

    public function scopeSharp(Builder $query)
    {
      return $query->where('type', 'sharp');
    }
  }

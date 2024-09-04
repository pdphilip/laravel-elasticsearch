<?php

  namespace Workbench\App\Models;

  use PDPhilip\Elasticsearch\Eloquent\Model;
  use PDPhilip\Elasticsearch\Eloquent\SoftDeletes;

  /** @property Carbon $deleted_at */
  class Soft extends Model
  {
    use SoftDeletes;

    protected $connection = 'elasticsearch';

    protected static $unguarded = true;
    protected $casts = ['deleted_at' => 'datetime'];

  }

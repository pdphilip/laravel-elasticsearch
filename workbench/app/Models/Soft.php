<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Eloquent\SoftDeletes;
use Workbench\Database\Factories\SoftFactory;

/** @property Carbon $deleted_at */
class Soft extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $connection = 'elasticsearch';

    protected static $unguarded = true;

    protected $casts = ['deleted_at' => 'datetime'];


  public static function newFactory(): SoftFactory
  {
    return SoftFactory::new();
  }
}

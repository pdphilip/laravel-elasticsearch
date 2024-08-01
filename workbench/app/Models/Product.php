<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model;

  /**
   * @mixin \Eloquent
   */
  class Product extends Model
  {
    use HasFactory;


    const MAX_SIZE = 10000;
    protected $connection = 'elasticsearch';
    protected $index      = 'my_products';

    protected $fillable = [
      'name',
      'price',
      'color',
      'status',
      'manufacturer.country',
      'is_active',
      'in_stock',
      'is_approved',
      'type',
    ];

  }

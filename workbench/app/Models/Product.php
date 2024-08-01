<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use PDPhilip\Elasticsearch\Eloquent\Model;
use Workbench\Database\Factories\ProductFactory;

/**
 * App\Models\Product
 *
 ******Fields*******
 *
 * @property string $_id
 * @property string $name
 * @property string $description
 * @property integer $in_stock
 * @property integer $price
 * @property integer $orders
 * @property array $order_values
 * @property integer $status
 * @property string $color
 * @property bool $is_active
 * @property array $manufacturer
 * @property string $datetime
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 ******Relationships*******
 * @property-read User $user
 *
 *
 * @mixin \Eloquent
 *
 */
  class Product extends Model
  {
    use HasFactory;

    protected $connection = 'elasticsearch';

    protected $fillable = [
      '_id',
      'name',
      'description',
      'in_stock',
      'is_active',
      'status',
      'color',
      'manufacturer',
      'price',
      'orders',
      'order_values',
      'product_id',
      'datetime',
      'last_order_datetime',
      'last_order_ts',
      'last_order_ms',
    ];

    public function getHasStockAttribute(): string
    {
      if ($this->in_stock > 0) {
        return 'yes';
      }

      return 'no';
    }

    public function getAvgOrdersAttribute(): float|int
    {
      $orders = array_filter($this->order_values);
      $avg = 0;
      if (count($orders)) {
        $avg = round(array_sum($orders) / count($orders));
      }

      return $avg;
    }

    //Relationships  =====================================

    public function user(): \PDPhilip\Elasticsearch\Relations\BelongsTo
    {
      return $this->belongsTo(User::class);
    }


    public static function newFactory(): ProductFactory
    {
      return ProductFactory::new();
    }

  }



<?php

  namespace Workbench\App\Models;
  use PDPhilip\Elasticsearch\Eloquent\Model;

  /**
   * App\Models\Photo
   *
   * @property int $id
   * @property string $url
   * @property string $photoable_id
   * @property string $photoable_type
   * @property \Illuminate\Support\Carbon|null $created_at
   * @property \Illuminate\Support\Carbon|null $updated_at
   * @mixin \Eloquent
   */
  class EsPhoto extends Model
  {
    protected $connection = 'elasticsearch';

    public function photoable()
    {
      return $this->morphTo();
    }

  }

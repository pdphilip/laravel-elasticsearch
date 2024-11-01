<?php

  declare(strict_types=1);

  namespace Workbench\App\Models;

  use PDPhilip\Elasticsearch\Eloquent\Model;

  /**
   * @property string $title
   * @property string $author
   * @property array $chapters
   */
  class Experience extends Model
  {
    protected $connection = 'elasticsearch';
    protected $table = 'experiences';
    protected static $unguarded = true;

    protected $casts = ['years' => 'int'];

    public function sqlUsers()
    {
      return $this->morphToMany(SqlUser::class, 'experienced');
    }
  }

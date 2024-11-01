<?php

  declare(strict_types=1);

  namespace Workbench\App\Models;

  use PDPhilip\Elasticsearch\Eloquent\Model;
  use PDPhilip\Elasticsearch\Relations\MorphToMany;

  /**
   * @property string $title
   * @property string $author
   * @property array $chapters
   */
  class Label extends Model
  {
    protected $connection = 'elasticsearch';
    protected $index = 'labels';
    protected static $unguarded = true;

    protected $fillable = [
      'name',
      'author',
      'chapters',
    ];

    public function users()
    {
      return $this->morphedByMany(User::class, 'labelled');
    }

    public function sqlUsers(): MorphToMany
    {
      return $this->morphedByMany(SqlUser::class, 'labeled');
    }

    public function clients()
    {
      return $this->morphedByMany(Client::class, 'labelled');
    }

    public function clientsWithCustomKeys()
    {
      return $this->morphedByMany(
        Client::class,
        'clabelled',
        'clabelleds',
        'clabel_ids',
        'cclabelled_id',
        'clabel_id',
        'cclient_id',
      );
    }
  }

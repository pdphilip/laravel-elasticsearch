<?php

  declare(strict_types=1);

  namespace Workbench\App\Models;

  use Illuminate\Database\Eloquent\Relations\MorphToMany;
  use PDPhilip\Elasticsearch\Eloquent\Model;
  use PDPhilip\Elasticsearch\Schema\IndexBlueprint;
  use PDPhilip\Elasticsearch\Schema\Schema;

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


    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
      $schema = Schema::connection('elasticsearch');

      $schema->deleteIfExists('labeleds');
      $schema->create('labeleds', function (IndexBlueprint $table) {
//        $table->string('skill_ids');
//        $table->string('sql_user_ids');
        $table->date('created_at');
        $table->date('updated_at');
      });

      $schema->deleteIfExists('labels');
      $schema->create('labels', function (IndexBlueprint $table) {
        $table->string('name');
        $table->string('author');
        $table->date('created_at');
        $table->date('updated_at');
      });
    }

  }

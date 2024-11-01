<?php

  declare(strict_types=1);

  namespace Workbench\App\Models;

  use PDPhilip\Elasticsearch\Eloquent\Model;
  use PDPhilip\Elasticsearch\Schema\IndexBlueprint;
  use PDPhilip\Elasticsearch\Schema\Schema;

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

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
      $schema = Schema::connection('elasticsearch');

      $schema->deleteIfExists('experienceds');
      $schema->create('experienceds', function (IndexBlueprint $table) {
//        $table->string('skill_ids');
//        $table->string('sql_user_ids');
        $table->date('created_at');
        $table->date('updated_at');
      });

      $schema->deleteIfExists('experiences');
      $schema->create('experiences', function (IndexBlueprint $table) {
        $table->string('name');
        $table->string('author');
        $table->date('created_at');
        $table->date('updated_at');
      });
    }

  }

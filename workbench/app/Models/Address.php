<?php

  declare(strict_types=1);

  namespace Workbench\App\Models;

  use PDPhilip\Elasticsearch\Eloquent\Model;
  use Illuminate\Database\Eloquent\Relations\BelongsTo;
  use PDPhilip\Elasticsearch\Schema\IndexBlueprint;
  use PDPhilip\Elasticsearch\Schema\Schema;

  /**
   * @property string $title
   * @property string $author
   * @property array $chapters
   */
  class Address extends Model
  {

    protected $connection = 'elasticsearch';
    protected $index = 'address';
    protected static $unguarded = true;

    /**
     * Check if we need to run the schema.
     */
    public static function executeSchema()
    {
      $schema = Schema::connection('elasticsearch');

      $schema->deleteIfExists('address');
      $schema->create('address', function (IndexBlueprint $table) {
        $table->date('created_at');
        $table->date('updated_at');
      });
    }

  }

<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Schema;
use PDPhilip\Elasticsearch\Schema\Blueprint;

class Post extends Model
{
    protected $connection = 'elasticsearch';
    protected $table = 'post';
    protected static $unguarded = true;

    protected array $mappingMap = [
      'comments.country' => 'comments.country.keyword',
      'comments.likes' => 'comments.likes'
    ];

  public static function executeSchema()
  {
    $schema = Schema::connection('elasticsearch');

    Schema::dropIfExists('post');

    $schema->create('post', function (Blueprint $table) {
      $table->text('title', hasKeyword: true);
      $table->integer('status');
      $table->nested('comments');

      $table->date('created_at');
      $table->date('updated_at');
    });


  }

}

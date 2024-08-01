<?php

  use Illuminate\Database\Migrations\Migration;
  use PDPhilip\Elasticsearch\Schema\Schema;
  use PDPhilip\Elasticsearch\Schema\IndexBlueprint;

  return new class extends Migration
  {
    public function up(): void
    {
      Schema::createIfNotExists('my_products', function (IndexBlueprint $index) {

        $index->text('name');
        $index->keyword('name');

        $index->float('price')->coerce(false);

        $index->keyword('color');
        $index->integer('status');

        $index->keyword('manufacturer.country');

        $index->boolean('is_active');
        $index->boolean('in_stock');
        $index->boolean('is_approved');

        $index->keyword('type');

        $index->date('created_at');
        $index->date('updated_at');

      });

    }

    public function down(): void
    {
      Schema::deleteIfExists('my_products');
    }
  };

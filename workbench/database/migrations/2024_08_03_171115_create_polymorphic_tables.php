<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use PDPhilip\Elasticsearch\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::deleteIfExists('videos');
        Schema::deleteIfExists('tags');
        Schema::deleteIfExists('taggables');


        Schema::create('videos', function ($index) {
            $index->text('name');
            $index->keyword('name');
            $index->date('created_at');
            $index->date('updated_at');
        });

        Schema::create('tags', function ($index) {
            $index->text('name');
            $index->keyword('name');
            $index->date('created_at');
            $index->date('updated_at');
        });

        Schema::create('taggables', function ($index) {

            $index->text('tag_id');
            $index->keyword('tag_id');
            $index->text('taggable_id');
            $index->keyword('taggable_id');
            $index->text('taggable_type');
            $index->keyword('taggable_type');

        });


    }

    public function down(): void
    {
      Schema::deleteIfExists('videos');
      Schema::deleteIfExists('tags');
      Schema::deleteIfExists('taggables');
    }
};

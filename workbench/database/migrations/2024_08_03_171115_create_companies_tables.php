<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use PDPhilip\Elasticsearch\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::deleteIfExists('companies');
        Schema::deleteIfExists('company_logs');
        Schema::deleteIfExists('avatars');
        Schema::deleteIfExists('photos');

        Schema::create('companies', function ($index) {
            $index->text('name');
            $index->integer('status');
            $index->date('created_at');
            $index->date('updated_at');
        });

        Schema::create('company_logs', function ($index) {
            $index->text('company_id');
            $index->text('title');
            $index->integer('code');
            $index->date('created_at');
            $index->date('updated_at');
        });

        Schema::create('avatars', function ($index) {
            $index->text('url');
            $index->text('imageable_id');
            $index->text('imageable_type');
            $index->date('created_at');
            $index->date('updated_at');
        });

        Schema::create('photos', function ($index) {
            $index->text('url');
            $index->text('photoable_id');
            $index->text('photoable_type');
            $index->date('created_at');
            $index->date('updated_at');
        });
    }

    public function down(): void
    {
        Schema::deleteIfExists('companies');
        Schema::deleteIfExists('company_logs');
        Schema::deleteIfExists('avatars');
        Schema::deleteIfExists('photos');
    }
};

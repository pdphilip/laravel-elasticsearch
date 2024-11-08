<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::deleteIfExists('static_pages');
        Schema::createIfNotExists('static_pages', function (Blueprint $index) {

            $index->text('title');
            $index->keyword('title');

            $index->text('content');

        });

    }

    public function down(): void
    {
        Schema::deleteIfExists('static_pages');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::deleteIfExists('softs');
        Schema::createIfNotExists('softs', function (Blueprint $index) {

            $index->text('name');
            $index->keyword('name');

            $index->date('created_at');
            $index->date('updated_at');
            $index->date('deleted_at');

        });

    }

    public function down(): void
    {
        Schema::deleteIfExists('softs');
    }
};

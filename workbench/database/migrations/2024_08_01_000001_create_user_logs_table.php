<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use PDPhilip\Elasticsearch\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::deleteIfExists('user_logs');

        Schema::create('user_logs', function ($index) {
            $index->text('user_id');
            $index->text('company_id');
            $index->text('title');
            $index->integer('code');
            $index->date('created_at');
            $index->date('updated_at');
        });
    }

    public function down(): void
    {
        Schema::deleteIfExists('user_logs');
    }
};

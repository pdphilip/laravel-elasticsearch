<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use PDPhilip\Elasticsearch\Schema\IndexBlueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::deleteIfExists('products');
        Schema::createIfNotExists('products', function (IndexBlueprint $index) {

            $index->text('name');
            $index->keyword('name');

            $index->text('description');
            $index->keyword('description');

            $index->text('product_id');
            $index->keyword('product_id');

            $index->integer('in_stock');

            $index->keyword('color');
            $index->integer('status');

            $index->boolean('is_active');

            $index->boolean('is_approved');

            $index->float('price');

            $index->integer('orders');
            $index->integer('order_values');

            $index->date('last_order_datetime');
            $index->date('last_order_ts');
            $index->date('last_order_ms');

            $index->geo('manufacturer.location');

            $index->keyword('manufacturer.name');
            $index->text('manufacturer.name');

            $index->text('manufacturer.country');
            $index->keyword('manufacturer.country');

            $index->keyword('manufacturer.owned_by.name');
            $index->keyword('manufacturer.owned_by.country');

            $index->keyword('type');

            $index->date('created_at');
            $index->date('updated_at');
            $index->date('deleted_at');

        });

    }

    public function down(): void
    {
        Schema::deleteIfExists('products');
    }
};

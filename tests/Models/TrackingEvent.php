<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use PDPhilip\Elasticsearch\Eloquent\GeneratesTimeOrderedIds;
use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Schema\Schema;

class TrackingEvent extends Model
{
    use GeneratesTimeOrderedIds;

    protected $connection = 'elasticsearch';

    protected static $unguarded = true;

    public static function executeSchema(): void
    {
        $schema = Schema::connection('elasticsearch');

        $schema->dropIfExists('tracking_events');
        $schema->create('tracking_events', function (Blueprint $table) {
            $table->keyword('event');
            $table->text('payload');
            $table->date('created_at');
            $table->date('updated_at');
        });
    }
}

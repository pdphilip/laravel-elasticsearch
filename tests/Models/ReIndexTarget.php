<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Tests\Concerns\TestsWithIdStrategies;

class ReIndexTarget extends Model
{
    use TestsWithIdStrategies;

    protected $connection = 'elasticsearch';

    protected $table = 're_index_targets';

    protected static $unguarded = true;

    public static function mappingDefinition(Blueprint $index): void
    {
        $index->keyword('status');
        $index->text('name');
        $index->date('created_at');
        $index->date('updated_at');
    }
}

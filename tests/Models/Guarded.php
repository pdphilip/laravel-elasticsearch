<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Tests\Concerns\TestsWithIdStrategies;

class Guarded extends Model
{
    use TestsWithIdStrategies;

    protected $connection = 'elasticsearch';

    protected $table = 'guarded';

    protected $guarded = ['foobar', 'level1->level2'];
}

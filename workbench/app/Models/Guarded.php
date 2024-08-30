<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use PDPhilip\Elasticsearch\Eloquent\Model;

class Guarded extends Model
{
    protected $connection = 'elasticsearch';

    protected $table = 'guarded';

    protected $guarded = ['foobar', 'level1->level2'];
}

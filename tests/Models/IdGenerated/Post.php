<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models\IdGenerated;

use PDPhilip\Elasticsearch\Eloquent\GeneratesUuids;
use PDPhilip\Elasticsearch\Tests\Models\Post as Base;

class Post extends Base
{
    use GeneratesUuids;
}

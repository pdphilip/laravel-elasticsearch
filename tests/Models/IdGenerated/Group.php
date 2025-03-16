<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models\IdGenerated;

use PDPhilip\Elasticsearch\Eloquent\GeneratesUuids;
use PDPhilip\Elasticsearch\Tests\Models\Group as Base;

class Group extends Base
{
    use GeneratesUuids;
}

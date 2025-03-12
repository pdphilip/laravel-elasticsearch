<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models\IdGenerated;

use PDPhilip\Elasticsearch\Eloquent\GeneratesUuids;
use PDPhilip\Elasticsearch\Tests\Models\Guarded as Base;

class Guarded extends Base
{
    use GeneratesUuids;
}

<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Tests\Models\IdGenerated;

use PDPhilip\Elasticsearch\Eloquent\GeneratesUuids;
use PDPhilip\Elasticsearch\Tests\Models\HiddenAnimal as Base;

class HiddenAnimal extends Base
{
    use GeneratesUuids;
}

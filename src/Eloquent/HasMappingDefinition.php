<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

use PDPhilip\Elasticsearch\Schema\Blueprint;

interface HasMappingDefinition
{
    public static function mappingDefinition(Blueprint $index): void;
}

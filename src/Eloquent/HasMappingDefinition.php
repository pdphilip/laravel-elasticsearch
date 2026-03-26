<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Eloquent;

use PDPhilip\Elasticsearch\Schema\Blueprint;

/**
 * @deprecated Override Model::mappingDefinition() directly instead.
 */
interface HasMappingDefinition
{
    public static function mappingDefinition(Blueprint $index): void;
}

<?php

namespace PDPhilip\Elasticsearch\Laravel\Compatibility\Schema;

use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Utils\Helpers;

trait GrammarCompatibility
{
    private function createBlueprint(Blueprint $blueprint): Blueprint
    {
        if (Helpers::getLaravelCompatabilityVersion() >= 12) {
            return new Blueprint($blueprint->getConnection(), '');
        }

        return new Blueprint(''); // @phpstan-ignore arguments.count
    }
}

<?php

namespace PDPhilip\Elasticsearch\Laravel\v11\Schema;

use PDPhilip\Elasticsearch\Schema\Blueprint;

trait GrammarCompatibility
{
    private function createBlueprint(Blueprint $blueprint): Blueprint
    {
        return new Blueprint('');
    }
}

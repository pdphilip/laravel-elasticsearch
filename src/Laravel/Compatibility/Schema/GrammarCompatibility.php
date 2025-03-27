<?php

namespace PDPhilip\Elasticsearch\Laravel\Compatibility\Schema;

use PDPhilip\Elasticsearch\Helpers\Helpers;
use PDPhilip\Elasticsearch\Laravel\v11\Schema\GrammarCompatibility as GrammarCompatibility11;
use PDPhilip\Elasticsearch\Laravel\v12\Schema\GrammarCompatibility as GrammarCompatibility12;

$laravelVersion = Helpers::getLaravelCompatabilityVersion();

if ($laravelVersion == 12) {
    trait GrammarCompatibility
    {
        use GrammarCompatibility12;
    }
} else {
    trait GrammarCompatibility
    {
        use GrammarCompatibility11;
    }
}

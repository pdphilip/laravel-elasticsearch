<?php

namespace PDPhilip\Elasticsearch\Laravel\Compatibility\Schema;

use PDPhilip\Elasticsearch\Laravel\v11\Schema\BuilderCompatibility as BuilderCompatibility11;
use PDPhilip\Elasticsearch\Laravel\v12\Schema\BuilderCompatibility as BuilderCompatibility12;
use PDPhilip\Elasticsearch\Utils\Helpers;

$laravelVersion = Helpers::getLaravelCompatabilityVersion();

if ($laravelVersion == 12) {
    trait BuilderCompatibility
    {
        use BuilderCompatibility12;
    }
} else {
    trait BuilderCompatibility
    {
        use BuilderCompatibility11;
    }
}

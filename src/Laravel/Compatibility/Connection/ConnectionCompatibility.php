<?php

namespace PDPhilip\Elasticsearch\Laravel\Compatibility\Connection;

use PDPhilip\Elasticsearch\Helpers\Helpers;
use PDPhilip\Elasticsearch\Laravel\v11\Connection\ConnectionCompatibility as ConnectionCompatibility11;
use PDPhilip\Elasticsearch\Laravel\v12\Connection\ConnectionCompatibility as ConnectionCompatibility12;

$laravelVersion = Helpers::getLaravelCompatabilityVersion();

if ($laravelVersion == 12) {
    trait ConnectionCompatibility
    {
        use ConnectionCompatibility12;
    }
} else {
    trait ConnectionCompatibility
    {
        use ConnectionCompatibility11;
    }
}

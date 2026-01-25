<?php

/**
 * IDE Helper for Laravel version compatibility traits.
 *
 * This file helps IDEs understand the runtime trait switching.
 * Points to v12 implementations as the canonical reference.
 *
 * @see /src/Laravel/Compatibility/
 */

namespace PDPhilip\Elasticsearch\Laravel\Compatibility\Schema {

    use PDPhilip\Elasticsearch\Laravel\v12\Schema\BlueprintCompatibility as BlueprintCompat12;
    use PDPhilip\Elasticsearch\Laravel\v12\Schema\BuilderCompatibility as BuilderCompat12;
    use PDPhilip\Elasticsearch\Laravel\v12\Schema\GrammarCompatibility as GrammarCompat12;

    trait BlueprintCompatibility
    {
        use BlueprintCompat12;
    }

    trait BuilderCompatibility
    {
        use BuilderCompat12;
    }

    trait GrammarCompatibility
    {
        use GrammarCompat12;
    }
}

namespace PDPhilip\Elasticsearch\Laravel\Compatibility\Connection {

    use PDPhilip\Elasticsearch\Laravel\v12\Connection\ConnectionCompatibility as ConnectionCompat12;

    trait ConnectionCompatibility
    {
        use ConnectionCompat12;
    }
}

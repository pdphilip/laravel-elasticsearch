<?php

namespace PDPhilip\Elasticsearch\Laravel\v11\Connection;

use PDPhilip\Elasticsearch\Query;
use PDPhilip\Elasticsearch\Schema;

trait ConnectionCompatibility
{
    /**
     * @return Schema\Grammars\Grammar
     */
    public function getSchemaGrammar()
    {
        return new Schema\Grammars\Grammar;
    }

    /** {@inheritdoc} */
    protected function getDefaultQueryGrammar(): Query\Grammar
    {
        return new Query\Grammar;
    }

    /** {@inheritdoc} */
    protected function getDefaultSchemaGrammar(): Schema\Grammars\Grammar
    {
        return new Schema\Grammars\Grammar;
    }
}

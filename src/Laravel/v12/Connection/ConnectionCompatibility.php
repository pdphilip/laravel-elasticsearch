<?php

namespace PDPhilip\Elasticsearch\Laravel\v12\Connection;

use PDPhilip\Elasticsearch\Query;
use PDPhilip\Elasticsearch\Schema;

trait ConnectionCompatibility
{
    /**
     * @return Schema\Grammars\Grammar
     */
    public function getSchemaGrammar()
    {
        return new Schema\Grammars\Grammar($this);
    }

    /** {@inheritdoc} */
    protected function getDefaultQueryGrammar(): Query\Grammar\Grammar
    {
        return new Query\Grammar\Grammar($this);
    }

    /** {@inheritdoc} */
    protected function getDefaultSchemaGrammar(): Schema\Grammars\Grammar
    {
        return new Schema\Grammars\Grammar($this);
    }
}

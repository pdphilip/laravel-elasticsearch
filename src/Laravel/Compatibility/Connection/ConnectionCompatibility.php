<?php

namespace PDPhilip\Elasticsearch\Laravel\Compatibility\Connection;

use PDPhilip\Elasticsearch\Query;
use PDPhilip\Elasticsearch\Schema;
use PDPhilip\Elasticsearch\Utils\Helpers;

trait ConnectionCompatibility
{
    /**
     * @return Schema\Grammars\Grammar
     */
    public function getSchemaGrammar()
    {
        return new Schema\Grammars\Grammar(...$this->grammarArgs());
    }

    /** {@inheritdoc} */
    protected function getDefaultQueryGrammar(): Query\Grammar\Grammar
    {
        return new Query\Grammar\Grammar(...$this->grammarArgs());
    }

    /** {@inheritdoc} */
    protected function getDefaultSchemaGrammar(): Schema\Grammars\Grammar
    {
        return new Schema\Grammars\Grammar(...$this->grammarArgs());
    }

    /** @phpstan-ignore return.type */
    private function grammarArgs(): array
    {
        return Helpers::getLaravelCompatabilityVersion() >= 12 ? [$this] : [];
    }
}

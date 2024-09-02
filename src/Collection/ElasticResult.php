<?php

namespace PDPhilip\Elasticsearch\Collection;

/**
 * WIP: This will be used to wrap the results of a query where the result is a single value
 */
class ElasticResult
{
    protected mixed $value;

    use ElasticCollectionMeta;

    public function __construct($value = null)
    {
        $this->value = $value;
    }

    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function __invoke()
    {
        return $this->value;
    }

    public function __toString()
    {
        return (string) $this->value;
    }
}

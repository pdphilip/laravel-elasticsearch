<?php

namespace PDPhilip\Elasticsearch\Query\Options;

/**
 * @method $this scoreMode(string $value)
 * @method $this ignoreUnmapped(bool $value)
 * @method $this innerHits(bool $value)
 */
class NestedOptions extends QueryOptions
{
    public function allowedOptions(): array
    {
        return [
            'score_mode',
            'ignore_unmapped',
            'inner_hits',
        ];
    }
}

<?php

namespace PDPhilip\Elasticsearch\Query\Options;

/**
 * @method $this scoreMode(string $value)
 * @method $this ignoreUnmapped(bool $value)
 */
class NestedOptions extends QueryOptions
{
    public function allowedOptions(): array
    {
        return [
            'score_mode',
            'ignore_unmapped',
        ];
    }
}

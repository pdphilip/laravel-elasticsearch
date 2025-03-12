<?php

namespace PDPhilip\Elasticsearch\Query\Options;

/**
 * @method $this rewrite(string $value)
 * @method $this caseInsensitive(bool $value)
 */
class PrefixOptions extends QueryOptions
{
    public function allowedOptions(): array
    {
        return [
            'rewrite',
            'case_insensitive',
        ];
    }
}

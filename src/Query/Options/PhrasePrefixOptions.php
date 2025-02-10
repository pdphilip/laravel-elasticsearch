<?php

namespace PDPhilip\Elasticsearch\Query\Options;

/**
 * @method $this analyzer(string $operator)
 * @method $this maxExpansions(int $value)
 * @method $this slop(int $value)
 * @method $this zeroTermsQuery(string $value)
 */
class PhrasePrefixOptions extends QueryOptions
{
    public function allowedOptions(): array
    {
        return [
            'analyzer',
            'max_expansions',
            'slop',
            'zero_terms_query',
        ];
    }
}

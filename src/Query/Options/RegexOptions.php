<?php

namespace PDPhilip\Elasticsearch\Query\Options;

/**
 * @method $this flags(string $value)
 * @method $this caseInsensitive(bool $value)
 * @method $this maxDeterminizedStates(int $value)
 * @method $this rewrite(string $value)
 */
class RegexOptions extends QueryOptions
{
    public function allowedOptions(): array
    {
        return [
            'flags',
            'case_insensitive',
            'max_determinized_states',
            'rewrite',
        ];
    }
}

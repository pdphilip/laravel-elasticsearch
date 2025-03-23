<?php

namespace PDPhilip\Elasticsearch\Query\Options;

/**
 * @method $this boost(float|int $value)
 * @method $this caseInsensitive(bool $value)
 */
class TermOptions extends QueryOptions
{
    public function allowedOptions(): array
    {
        return [
            'boost',
            'case_insensitive',
        ];
    }

    public function asCaseInsensitive()
    {
        return $this->caseInsensitive(true);
    }
}

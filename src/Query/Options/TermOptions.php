<?php

namespace PDPhilip\Elasticsearch\Query\Options;

/**
 * @method TermOptions boost(float|int $value)
 * @method TermOptions caseInsensitive(bool $value)
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

<?php

namespace PDPhilip\Elasticsearch\Query\Options;

/**
 * @method $this boost(float|int $value)
 * @method $this format(string $value)
 * @method $this timeZone(string $value)
 * @method $this relation(string $value) $value must be one of the following: INTERSECTS (default), CONTAINS, WITHIN
 */
class DateOptions extends QueryOptions
{
    public function allowedOptions(): array
    {
        return [
            'boost',
            'format',
            'time_zone',
            'relation',
        ];
    }
}

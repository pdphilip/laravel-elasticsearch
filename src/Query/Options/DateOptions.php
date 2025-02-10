<?php

namespace PDPhilip\Elasticsearch\Query\Options;

/**
 * @method DateOptions boost(float|int $value)
 * @method DateOptions format(string $value)
 * @method DateOptions timeZone(string $value)
 * @method DateOptions relation(string $value) $value must be one of the following: INTERSECTS (default), CONTAINS, WITHIN
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

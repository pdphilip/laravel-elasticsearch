<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Helpers;

trait Utilities
{
    public function _escape($value): string
    {
        $specialChars = ['"', '\\', '~', '^', '/'];
        foreach ($specialChars as $char) {
            $value = str_replace($char, '\\'.$char, $value);
        }
        if (str_starts_with($value, '-')) {
            $value = '\\'.$value;
        }

        return $value;
    }
}

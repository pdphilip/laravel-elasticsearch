<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Helpers;

use Closure;
use Illuminate\Support\Str;

/**
 * @internal
 */
final class Helpers
{
    public static function escape($value): string
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

      public static function uuid()
      {
        // this is the equivelent of how elasticsearch generates UUID
        // see: https://github.com/elastic/elasticsearch/blob/2f2ddad00492fcac8fbfc272607a8db91d279385/server/src/main/java/org/elasticsearch/common/TimeBasedUUIDGenerator.java#L67
        return base64_encode((string) Str::orderedUuid());
      }

    /**
     * Return the default value of the given value.
     */
    public static function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

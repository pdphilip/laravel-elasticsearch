<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Utils;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Exceptions\RuntimeException;

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

    public static function uuid(): string
    {
        // this is the equivalent of how elasticsearch generates UUID
        // see: https://github.com/elastic/elasticsearch/blob/2f2ddad00492fcac8fbfc272607a8db91d279385/server/src/main/java/org/elasticsearch/common/TimeBasedUUIDGenerator.java#L67
        return base64_encode((string) Str::orderedUuid());
    }

    public static function timeBasedUUID(): string
    {
        return TimeBasedUUIDGenerator::generate();
    }

    /**
     * Return the default value of the given value.
     */
    public static function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }

    public static function getLaravelCompatabilityVersion(): int
    {
        $majorVersion = (int) Str::before(Application::VERSION, '.');
        if ($majorVersion === 10) {
            $majorVersion = 11;
        }
        if (! in_array($majorVersion, [11, 12], true)) {
            throw new RuntimeException('Laravel version not supported [found: '.Application::VERSION.']. Supported Major versions are 10/11 and 12.');
        }

        return $majorVersion;
    }

    public static function sanitizeRanges($input)
    {
        $ranges = [];
        foreach ($input as $value) {
            $range = [];
            if (isset($value['from']) || isset($value['to'])) {
                if (! empty($value['from'])) {
                    $range['from'] = $value['from'];
                }
                if (! empty($value['to'])) {
                    $range['to'] = $value['to'];
                }

                if (! empty($value['key'])) {
                    $range['key'] = $value['key'];
                }
                $ranges[] = $range;

                continue;
            }
            // assume it's an array with values of from and to
            if ($value[0] !== null) {
                $range['from'] = $value[0];
            }
            if (isset($value[1])) {
                $range['to'] = $value[1];
            }
            $ranges[] = $range;

        }

        return $ranges;
    }
}

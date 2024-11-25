<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Helpers;

use Closure;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\Eloquent\Model;
use ReflectionClass;

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

  /**
   * Calls
   *
   * @param mixed $resource
   * @param string $method
   * @param array  $parameters
   *
   * @return null
   */
  public static function callMethods(mixed $resource, string $method, array $parameters = [])
  {

    // this is a cool trick that gets all traits used by this trait and allows us to call methods using their name.
    $traits = Helpers::traitUsesRecursive('PDPhilip\Elasticsearch\Eloquent\ElasticsearchModel');

    foreach ($traits as $trait) {
      $traitReflection = new ReflectionClass($trait);
      $methodName = $method . $traitReflection->getShortName();
      if (method_exists($resource, $methodName)) {
        return $resource->$methodName(...$parameters);
      }
    }

    return null;
  }

    /**
     * Returns all traits used by a trait and its traits.
     *
     * Credits To Saloon 3
     * @link: https://github.com/saloonphp/saloon/blob/v3/src/Helpers/Helpers.php
     *
     * @param class-string $trait
     * @return array<class-string, class-string>
     */
    public static function traitUsesRecursive(string $trait): array
    {
      /** @var array<class-string, class-string> $traits */
      $traits = class_uses($trait) ?: [];

      foreach ($traits as $trait) {
        $traits += static::traitUsesRecursive($trait);
      }

      return $traits;
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

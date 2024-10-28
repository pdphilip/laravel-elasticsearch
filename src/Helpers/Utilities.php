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

  /**
   * Convert column ID
   *
   * Converts the column name from 'id' to '_id'.
   *
   * @param string $value The column name to convert.
   *
   * @return string The converted column name.
   */
  public function convertColumnID(string $value): string
  {
    return $value == 'id' ? '_id' : $value;
  }
}

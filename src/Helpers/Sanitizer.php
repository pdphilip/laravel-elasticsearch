<?php

namespace PDPhilip\Elasticsearch\Helpers;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final class Sanitizer
{
    public static function flattenFieldMapping(array $mapping)
    {
        $fields = [];
        if (! empty($mapping['mappings'])) {
            foreach ($mapping['mappings'] as $key => $item) {
                if (! empty($item['mapping'])) {
                    foreach ($item['mapping'] as $details) {
                        if (isset($details['type'])) {
                            $fields[$key] = $details['type'];
                        }
                        if (isset($details['fields'])) {
                            foreach ($details['fields'] as $subField => $subDetails) {
                                $subFieldName = $key.'.'.$subField;
                                $fields[$subFieldName] = $subDetails['type'];
                            }
                        }
                    }
                }
            }
        }
        $mappings = Collection::make($fields);
        $mappings = $mappings->sortKeys();

        return $mappings->toArray();
    }

    public static function flattenMappingProperties(array $mapping, string $prefix = ''): array
    {
        $sanitized = array_filter($mapping, function ($key) {
            return $key !== 'properties';
        }, ARRAY_FILTER_USE_KEY);

        if (isset($mapping['properties']) && is_array($mapping['properties'])) {
            foreach ($mapping['properties'] as $field => $details) {
                $fullKey = $prefix ? "$prefix.$field" : $field;

                $sanitized[$fullKey] = $details;

                if (isset($details['properties']) && is_array($details['properties'])) {
                    $sanitized = array_merge($sanitized, self::flattenMappingProperties(['properties' => $details['properties']], $fullKey));
                    unset($sanitized[$fullKey]['properties']);
                }
                if (isset($details['fields']) && is_array($details['fields'])) {
                    $sanitized = array_merge($sanitized, self::flattenMappingProperties(['properties' => $details['fields']], $fullKey));
                    unset($sanitized[$fullKey]['fields']);
                }
            }
        }

        return $sanitized;
    }

    public static function qualifiedIndex($prefix, $table, $suffix)
    {
        $table = str_replace($prefix, '', $table);
        $table = str_replace($suffix, '', $table);

        return $prefix.$table.$suffix;
    }

    public static function cleanArrayValues($input): array
    {
        $groups = [];
        if ($input) {
            foreach ($input as $val) {
                $groups = array_merge(
                    (array) $groups,
                    Arr::wrap($val)
                );
            }
        }

        return $groups;
    }
}

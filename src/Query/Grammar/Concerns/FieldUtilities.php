<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query\Grammar\Concerns;

use DateTime;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use PDPhilip\Elasticsearch\Exceptions\BuilderException;
use PDPhilip\Elasticsearch\Query\Builder;
use PDPhilip\Elasticsearch\Schema\Schema;

/**
 * Field mapping, value conversion, and other utility methods.
 */
trait FieldUtilities
{
    /**
     * Get value for the where clause.
     */
    protected function getValueForWhere(?Builder $builder, array $where): mixed
    {
        $value = match ($where['type']) {
            'In', 'Between' => $where['values'],
            'Exists' => null,
            default => $where['value'],
        };

        return $this->getStringValue($value);
    }

    /**
     * Convert values to their string representation.
     */
    protected function getStringValue($value)
    {
        if ($value instanceof DateTime) {
            $value = $this->convertDateTime($value);
        } elseif (is_array($value)) {
            foreach ($value as &$val) {
                if ($val instanceof DateTime) {
                    $val = $this->convertDateTime($val);
                }
            }
        }

        return $value;
    }

    /**
     * DateTime to ISO 8601 string.
     */
    protected function convertDateTime($value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return $value->format('c');
    }

    /**
     * Prepend parent field path for nested queries.
     */
    public function prependParentField($field, Builder $builder): string
    {
        if (! empty($parentField = $builder->options()->get('parentField'))) {
            if (str_starts_with($field, $parentField)) {
                return $field;
            }

            return $parentField.'.'.$field;
        }

        return $field;
    }

    /**
     * Find the indexable (keyword) subfield for a given field.
     *
     * @throws BuilderException
     */
    public function getIndexableField(string $textField, Builder $builder): string
    {
        // Special fields don't need mapping lookup
        if ($textField == '_id' || $textField == 'id') {
            return '_id';
        }
        if (in_array($textField, ['_count', '_score'])) {
            return $textField;
        }

        // Check for explicit field mapping override
        if (! empty($queryFieldMap = $builder->options()->get(\PDPhilip\Elasticsearch\Eloquent\Model::OPTION_MAPPING_MAP)) && ! empty($queryFieldMap[$textField])) {
            return $queryFieldMap[$textField];
        }

        // Skip validation if configured to do so
        if ($builder->connection->options()->get('bypass_map_validation')) {
            return $textField;
        }

        // Cache the mapping lookup per index
        $cacheKey = $builder->from.'_mapping_cache';
        $mapping = $builder->options()->get($cacheKey);
        if (! $mapping) {
            $mapping = collect(Arr::dot($builder->getMapping()));
            $builder->options()->add($cacheKey, $mapping);
        }

        // Find a non-text field (keyword, number, date, etc.)
        $keywordKey = $mapping->keys()
            ->filter(fn ($field) => str_starts_with($field, $textField) && ! in_array($mapping[$field], ['text', 'binary']))
            ->first();

        if (! empty($keywordKey)) {
            return $keywordKey;
        }

        throw new BuilderException("{$textField} does not have a keyword field.");
    }

    /**
     * Get the nested mapping path for a dotted field.
     * For 'tags.key', returns 'tags' if tags is mapped as nested.
     * Supports multi-level nesting: 'a.b.c' checks 'a' then 'a.b'.
     *
     * Uses the index mapping API (getMappings) rather than the field mapping API,
     * because the field mapping API doesn't return nested parent entries.
     */
    public function getNestedPath(string $field, Builder $builder): ?string
    {
        if (! str_contains($field, '.')) {
            return null;
        }

        $nestedPaths = $this->resolveNestedPaths($builder);

        $segments = explode('.', $field);
        $path = '';
        for ($i = 0; $i < count($segments) - 1; $i++) {
            $path = $path ? $path.'.'.$segments[$i] : $segments[$i];
            if (in_array($path, $nestedPaths)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Resolve all nested field paths for the builder's index.
     * Cached per index to avoid repeated API calls.
     */
    protected function resolveNestedPaths(Builder $builder): array
    {
        $cacheKey = $builder->from.'_nested_paths';
        $cached = $builder->options()->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $nestedPaths = [];
        try {
            $mapping = Schema::connection($builder->connection->getName())->getMappings($builder->getFrom());
            foreach ($mapping as $field => $details) {
                if (is_array($details) && ($details['type'] ?? null) === 'nested') {
                    $nestedPaths[] = $field;
                }
            }
        } catch (\Exception) {
            // Index may not exist yet — no nested paths to detect
        }

        $builder->options()->add($cacheKey, $nestedPaths);

        return $nestedPaths;
    }

    /**
     * Date format from config.
     */
    public function getDateFormat(): string
    {
        return Config::get('laravel-elasticsearch.date_format', 'Y-m-d H:i:s');
    }

    /**
     * Filter options array to only allowed keys.
     */
    private function getAllowedOptions(array $options, array $allowed): array
    {
        return array_intersect_key($options, array_flip($allowed));
    }

    /**
     * Parse operators that imply negation.
     * Returns [normalized_operator, is_negated].
     */
    protected function parseNegationOperator($operator, $value): array
    {
        if ($operator == 'not like' && ! is_null($value)) {
            return ['like', true];
        }
        if ($operator == '<>' && ! is_null($value)) {
            return [$operator, true];
        }
        if ($operator == '!=' && ! is_null($value)) {
            return ['=', true];
        }
        if ($operator == '=' && is_null($value)) {
            return [$operator, true];
        }
        if ($operator == 'exists' && ! $value) {
            return [$operator, true];
        }

        return [$operator, false];
    }
}

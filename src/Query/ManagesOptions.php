<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query;

use Exception;
use PDPhilip\Elasticsearch\Query\Options\ClauseOptions;
use PDPhilip\Elasticsearch\Query\Options\DateOptions;
use PDPhilip\Elasticsearch\Query\Options\FuzzyOptions;
use PDPhilip\Elasticsearch\Query\Options\MatchOptions;
use PDPhilip\Elasticsearch\Query\Options\NestedOptions;
use PDPhilip\Elasticsearch\Query\Options\PhraseOptions;
use PDPhilip\Elasticsearch\Query\Options\PhrasePrefixOptions;
use PDPhilip\Elasticsearch\Query\Options\PrefixOptions;
use PDPhilip\Elasticsearch\Query\Options\QueryStringOptions;
use PDPhilip\Elasticsearch\Query\Options\RegexOptions;
use PDPhilip\Elasticsearch\Query\Options\SearchOptions;
use PDPhilip\Elasticsearch\Query\Options\TermOptions;
use PDPhilip\Elasticsearch\Query\Options\TermsOptions;
use ReflectionFunction;

trait ManagesOptions
{
    protected function modifyOptions(array $options = [], $type = 'wheres'): static
    {
        // Get the last added "where" condition
        $lastWhere = array_pop($this->$type);

        // Append the new options to it
        $lastWhere = array_merge($lastWhere, [
            'options' => $options,
        ]);

        $this->$type[] = $lastWhere;

        return $this;
    }

    /**
     * Adds additional options to the last added "where" condition.
     *
     * @param  array  $options  An associative array of options to add.
     * @return static Returns the instance with updated options.
     */
    public function applyOptions(array $options = []): static
    {
        return $this->modifyOptions($options);
    }

    /**
     * Adds additional options to the last added "filter" condition.
     *
     * @param  array  $options  An associative array of options to add.
     * @return static Returns the instance with updated options.
     */
    public function withFilterOptions(array $options = []): static
    {
        return $this->modifyOptions($options, 'filters');
    }

    public function extractOptionsWithOperator($type, $column, $operator, $value, $boolean, $options): array
    {
        $not = false;
        [$column, $operator, $value, $boolean, $not, $options] = $this->extractOptionsFull($type, $column, $operator, $value, $boolean, $not, $options);

        return [$column, $operator, $value, $boolean, $options];
    }

    public function extractOptionsWithNot($type, $column, $value, $boolean, $not, $options = []): array
    {
        $operator = null;
        [$column, $operator, $value, $boolean, $not, $options] = $this->extractOptionsFull($type, $column, $operator, $value, $boolean, $not, $options);

        return [$column, $value, $not, $boolean, $options];
    }

    public function extractSearch($columns = null, $options = [], $as = 'search'): array
    {
        if ($options) {
            return [$columns, $options];
        }
        if (is_callable($columns) && ! is_string($columns)) {
            return [null, $columns];
        }
        if (is_array($columns) && $this->validatePossibleOptions($columns, $as)) {
            return [null, $columns];
        }

        return [$columns, $options];
    }

    /**
     * Extract and normalize parameters, detecting when options were passed in place of other arguments.
     *
     * Resolution order (first match wins):
     *   1. $column is a closure → nested query, return as-is
     *   2. $options is populated → parse and return
     *   3. $not is not a bool → treat as options (reset $not to false)
     *   4. $boolean is not a string → treat as options (reset $boolean to 'and')
     *   5. $value is callable or array of valid option keys → treat as options (set $value to null)
     *   6. $operator is callable or array of valid option keys → treat as options (set $operator to null)
     *   7. No options detected → return as-is
     */
    protected function extractOptionsFull($type, $column, $operator, $value, $boolean, $not, $options = []): array
    {
        if (is_callable($column) && ! is_string($column)) {
            return [$column, $operator, $value, $boolean, $not, $options];
        }

        if ($options) {
            return [$column, $operator, $value, $boolean, $not, $this->parseOptions($options, $type)];
        }

        if (! is_bool($not)) {
            return [$column, $operator, $value, $boolean, false, $this->parseOptions($not, $type)];
        }

        if (! is_string($boolean)) {
            return [$column, $operator, $value, 'and', $not, $this->parseOptions($boolean, $type)];
        }

        $allowedKeys = $this->getAllowedOptionKeys();

        if ($value && ! is_string($value)) {
            if (is_callable($value) || (is_array($value) && count(array_intersect(array_keys($value), $allowedKeys)))) {
                return [$column, $operator, null, $boolean, $not, $this->parseOptions($value, $type)];
            }
        }

        if ($operator && ! is_string($operator)) {
            if (is_callable($operator) || (is_array($operator) && count(array_intersect(array_keys($operator), $allowedKeys)))) {
                return [$column, null, $value, $boolean, $not, $this->parseOptions($operator, $type)];
            }
        }

        return [$column, $operator, $value, $boolean, $not, $options];
    }

    /**
     * Parse a value that may be options: an array is returned as-is,
     * a closure is invoked with the appropriate typed options class.
     *
     * When $type is provided, the option class is resolved directly via getOptionClass().
     * Falls back to Reflection on the closure's type hint when $type is unavailable.
     */
    protected function parseOptions($value, ?string $type = null)
    {
        if (is_callable($value)) {
            // Resolve option class from the query type when available (no Reflection needed)
            $class = $type ? $this->getOptionClass($type) : null;

            if ($class) {
                return tap(new $class, $value)->toArray();
            }

            // Fallback: infer option class from the closure's type hint
            try {
                $reflection = new ReflectionFunction($value);
                $paramType = $reflection->getParameters()[0]->getType();
                $class = $paramType->getName();

                return tap(new $class, $value)->toArray();
            } catch (Exception $e) {
                // Callable didn't match expected option signature — return empty options
                return [];
            }
        }

        if (is_array($value)) {
            return $value;
        }

        return [];
    }

    public function setOptions($options, $type)
    {
        $optionClass = $this->getOptionClass($type);
        if (! $optionClass) {
            throw new \Exception('Invalid option type: '.$type);
        }
        if (is_callable($options)) {
            return tap(new $optionClass, $options);
        }
        if (is_array($options)) {
            return new $optionClass($options);
        }

        return new $optionClass;
    }

    protected function getAllowedOptionKeys(): array
    {
        return (new ClauseOptions)->allowedOptions();
    }

    /**
     * @deprecated Use getAllowedOptionKeys() instead.
     */
    protected function returnAllowedOptions(): array
    {
        return $this->getAllowedOptionKeys();
    }

    protected function getOptionClass($type): ?string
    {
        return match (strtolower($type)) {
            'clause', 'where', 'basic' => ClauseOptions::class,
            'term' => TermOptions::class,
            'terms' => TermsOptions::class,
            'date' => DateOptions::class,
            'phrase' => PhraseOptions::class,
            'phraseprefix' => PhrasePrefixOptions::class,
            'search' => SearchOptions::class,
            'match' => MatchOptions::class,
            'fuzzy', 'termfuzzy' => FuzzyOptions::class,
            'prefix' => PrefixOptions::class,
            'regex' => RegexOptions::class,
            'nested' => NestedOptions::class,
            'querystring' => QueryStringOptions::class,
            default => null
        };
    }

    protected function validatePossibleOptions(array $data, $type): bool
    {
        $optionClass = $this->getOptionClass($type);
        $allowed = (new $optionClass)->allowedOptions();
        $keys = array_keys($data);

        return ! empty($keys) && empty(array_diff($keys, $allowed));
    }
}

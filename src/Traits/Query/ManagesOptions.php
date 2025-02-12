<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Traits\Query;

use PDPhilip\Elasticsearch\Query\Options\DateOptions;
use PDPhilip\Elasticsearch\Query\Options\NestedOptions;
use PDPhilip\Elasticsearch\Query\Options\PhraseOptions;
use PDPhilip\Elasticsearch\Query\Options\PhrasePrefixOptions;
use PDPhilip\Elasticsearch\Query\Options\PrefixOptions;
use PDPhilip\Elasticsearch\Query\Options\RegexOptions;
use PDPhilip\Elasticsearch\Query\Options\SearchOptions;
use PDPhilip\Elasticsearch\Query\Options\TermFuzzyOptions;
use PDPhilip\Elasticsearch\Query\Options\TermOptions;
use PDPhilip\Elasticsearch\Query\Options\WhereOptions;

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

    protected function extractOptionsFull($type, $column, $operator, $value, $boolean, $not, $options = []): array
    {
        if (is_callable($column)) {
            // The query is a closure, return it as is
            return [$column, $operator, $value, $boolean, $not, $options];
        }

        // If options are passed, then we have them here
        if ($options) {
            $options = $this->parseOptions($options, $type);

            return [$column, $operator, $value, $boolean, $not, $options];
        }

        // If $not is not a boolean, then it's something else, we assume options here
        if (! is_bool($not)) {
            $options = $this->parseOptions($not, $type);
            $not = false;

            return [$column, $operator, $value, $boolean, $not, $options];
        }

        // If boolean is not a string then it's not a boolean, we assume options here
        if (! is_string($boolean)) {
            $options = $this->parseOptions($boolean, $type);
            $boolean = 'and';

            return [$column, $operator, $value, $boolean, $not, $options];
        }
        $allowedOptions = $this->returnAllowedOptions($type);

        if ($value) {
            // If either is callable or an array containing valid operators, then we have options
            if (is_callable($value) || (is_array($value) && count(array_intersect(array_keys($value), $allowedOptions)))) {
                $options = $this->parseOptions($value, $type);
                $value = null;

                return [$column, $operator, $value, $boolean, $not, $options];

            }
        }

        // Last, let's assess the operator
        if ($operator) {
            // If either is callable or an array containing valid operators, then we have options
            if (is_callable($operator) || (is_array($operator) && count(array_intersect(array_keys($operator), $allowedOptions)))) {
                $options = $this->parseOptions($operator, $type);
                $operator = null;

                return [$column, $operator, $value, $boolean, $not, $options];

            }

        }

        // Ok, we tried. We have no options, return as is
        return [$column, $operator, $value, $boolean, $not, $options];
    }

    protected function parseOptions($value, $type)
    {
        return $this->setOptions($value, $type)->toArray();
    }

    public function setOptions($options, $type)
    {
        $optionClass = $this->getOptionClass($type);
        if (! $optionClass) {
            // remove after testing as it's internal and should never happen
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

    protected function returnAllowedOptions($type): array
    {
        return $this->setOptions([], $type)->allowedOptions();
    }

    protected function getOptionClass($type)
    {
        return match (strtolower($type)) {
            'where' => WhereOptions::class,
            'date' => DateOptions::class,
            'phrase' => PhraseOptions::class,
            'phraseprefix' => PhrasePrefixOptions::class,
            'search' => SearchOptions::class,
            'term' => TermOptions::class,
            'termfuzzy' => TermFuzzyOptions::class,
            'prefix' => PrefixOptions::class,
            'regex' => RegexOptions::class,
            'nested' => NestedOptions::class,
            default => null
        };
    }
}

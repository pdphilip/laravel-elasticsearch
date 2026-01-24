<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query\Concerns;

use InvalidArgumentException;

/**
 * Handles script-based operations for Elasticsearch queries.
 *
 * @property array $scripts
 * @property array $wheres
 *
 * @mixin \PDPhilip\Elasticsearch\Query\Builder
 */
trait HandlesScripts
{
    /**
     * Add a script query.
     *
     * @param  string  $boolean
     */
    public function whereScript(string $script, array $options = [], $boolean = 'and'): self
    {
        $type = 'Script';

        $this->wheres[] = compact('script', 'boolean', 'type', 'options');

        return $this;
    }

    public function orWhereScript(string $script, array $options = []): self
    {
        return $this->whereScript($script, $options, 'or');
    }

    /**
     * Remove one or more values from an array.
     *
     * @param  string|array  $column
     * @param  mixed  $value
     * @return int
     */
    public function pull($column, $value = null)
    {
        $value = is_array($value) ? $value : [$value];

        // Prepare the script for pulling/removing values.
        $script = "
        if (ctx._source.{$column} != null) {
            ctx._source.{$column}.removeIf(item -> {
                for (removeItem in params.pull_values) {
                    if (item == removeItem) {
                        return true;
                  }
                }
                return false;
            });
        }
    ";

        $options['params'] = ['pull_values' => $value];
        $this->scripts[] = compact('script', 'options');

        return $this->update([]);
    }

    /**
     * Append one or more values to an array.
     *
     * @param  string|array  $column
     * @param  mixed  $value
     * @param  bool  $unique
     * @return int
     */
    public function push($column, $value = null, $unique = false)
    {
        // Check if we are pushing multiple values.
        $batch = is_array($value) && array_is_list($value);

        $value = $batch ? $value : [$value];

        // Prepare the script for unique or non-unique addition.
        if ($unique) {
            $script = "
            if (ctx._source.{$column} == null) {
                ctx._source.{$column} = [];
            }
            for (item in params.push_values) {
                if (!ctx._source.{$column}.contains(item)) {
                    ctx._source.{$column}.add(item);
                }
            }
        ";
        } else {
            $script = "
            if (ctx._source.{$column} == null) {
                ctx._source.{$column} = [];
            }
            ctx._source.{$column}.addAll(params.push_values);
        ";
        }

        $options['params'] = ['push_values' => $value];
        $this->scripts[] = compact('script', 'options');

        return $this->update([]);
    }

    /**
     * Build and add increment or decrement scripts for the given columns.
     *
     * @param  array  $columns  Associative array of columns and their corresponding increment/decrement amounts.
     * @param  string  $type  Type of operation, either 'increment' or 'decrement'.
     * @param  array  $extra  Additional options for the update.
     * @return mixed The result of the update operation.
     *
     * @throws InvalidArgumentException If a non-numeric value is passed as an increment amount
     *                                  or a non-associative array is passed to the method.
     */
    protected function buildCrementEach(array $columns, string $type, array $extra = [])
    {
        foreach ($columns as $column => $amount) {
            if (! is_numeric($amount)) {
                throw new InvalidArgumentException("Non-numeric value passed as increment amount for column: '$column'.");
            } elseif (! is_string($column)) {
                throw new InvalidArgumentException('Non-associative array passed to incrementEach method.');
            }

            $operator = $type == 'increment' ? '+' : '-';

            $script = implode('', [
                "if (ctx._source.{$column} == null) { ctx._source.{$column} = 0; }",
                "ctx._source.{$column} $operator= params.{$type}_{$column}_value;",
            ]);

            $options['params'] = ["{$type}_{$column}_value" => (int) $amount];

            $this->scripts[] = compact('script', 'options');
        }

        if (empty($this->wheres)) {
            $this->wheres[] = [
                'type' => 'MatchAll',
                'boolean' => 'and',
            ];
        }

        return $this->update($extra);
    }
}

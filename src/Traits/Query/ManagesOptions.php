<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Traits\Query;

trait ManagesOptions
{
    /**
     * Adds additional options to the last added "where" condition.
     *
     * @param  array  $options  An associative array of options to add.
     * @return static Returns the instance with updated options.
     */
    public function withOptions(array $options = []): static
    {
        // Get the last added "where" condition
        $lastWhere = array_pop($this->wheres);

        // Append the new options to it
        $lastWhere = array_merge($lastWhere, [
            'options' => $options ?? [],
        ]);

        $this->wheres[] = $lastWhere;

        return $this;
    }

    /**
     * Adds additional options to the last added "filter" condition.
     *
     * @param  array  $options  An associative array of options to add.
     * @return static Returns the instance with updated options.
     */
    public function withFilterParameters(array $options = []): static
    {
        // Get the last added "where" condition
        $lastWhere = array_pop($this->filters);

        // Append the new options to it
        $lastWhere = array_merge($lastWhere, [
            'options' => $options ?? [],
        ]);

        $this->filters[] = $lastWhere;

        return $this;
    }
}

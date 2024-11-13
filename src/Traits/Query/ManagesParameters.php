<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Traits\Query;

trait ManagesParameters
{
    /**
     * Adds additional parameters to the last added "where" condition.
     *
     * @param  array  $parameters  An associative array of parameters to add.
     * @return static Returns the instance with updated parameters.
     */
    public function withParameters(array $parameters = []): static
    {
        // Get the last added "where" condition
        $lastWhere = array_pop($this->wheres);

        // Append the new parameters to it
        $lastWhere = array_merge($lastWhere, [
            'parameters' => $parameters ?? [],
        ]);

        $this->wheres[] = $lastWhere;

        return $this;
    }

    /**
     * Adds additional parameters to the last added "filter" condition.
     *
     * @param  array  $parameters  An associative array of parameters to add.
     * @return static Returns the instance with updated parameters.
     */
    public function withFilterParameters(array $parameters = []): static
    {
        // Get the last added "where" condition
        $lastWhere = array_pop($this->filters);

        // Append the new parameters to it
        $lastWhere = array_merge($lastWhere, [
            'parameters' => $parameters ?? [],
        ]);

        $this->filters[] = $lastWhere;

        return $this;
    }
}

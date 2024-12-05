<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Traits\Query;

trait ManagesOptions
{
    protected function modifyOptions(array $options = [], $type = 'wheres'): static
    {
        // Get the last added "where" condition
        $lastWhere = array_pop($this->$type);

        // Append the new options to it
        $lastWhere = array_merge($lastWhere, [
            'options' => $options ?? [],
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
    public function withOptions(array $options = []): static
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
}

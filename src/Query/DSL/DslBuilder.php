<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Query\DSL;

use RuntimeException;

class DslBuilder
{
    protected array $dsl = [];

    protected array $path = [];

    public static function make(): self
    {
        return new static;
    }

    // ----------------------------------------------------------------------
    // Getters
    // ----------------------------------------------------------------------

    public function getDsl(): array
    {
        return $this->dsl;
    }

    public function getBodyValue(array $keys)
    {
        return $this->getValueAtPath($this->dsl, ['body', ...$keys]);
    }

    // ----------------------------------------------------------------------
    // Setters
    // ----------------------------------------------------------------------

    public function setIndex($index)
    {
        return $this->set(['index'], $index);
    }

    public function setBody(array $keys, $value)
    {
        return $this->set(['body', ...$keys], $value);
    }

    public function unsetBody(array $keys)
    {
        $this->unsetKeyAtPath($this->dsl, ['body', ...$keys]);
    }

    public function setFrom(int $from): self
    {
        return $this->set(['from'], $from);
    }

    /**
     * Set the size part (for pagination)
     */
    public function setSize(int $size): self
    {
        return $this->set(['size'], $size);
    }

    /**
     * Set the _source part (for field selection)
     */
    public function setSource($fields): self
    {
        return $this->set(['_source'], $fields);
    }

    /**
     * Set the highlight part
     */
    public function setHighlight(array $highlight): self
    {
        return $this->set(['highlight'], $highlight);
    }

    /**
     * Add an aggregation
     */
    public function setAggregation(string $name, array $aggregation): self
    {
        return $this->set(['aggs', $name], $aggregation);
    }

    /**
     * Set the script part for updates
     */
    public function setScript(string $source, array $params = [], array $options = []): self
    {
        $script = array_merge(['source' => $source, 'params' => $params], $options);

        return $this->set(['script'], $script);
    }

    /**
     * Add fields to the fields array (_source restriction)
     */
    public function setFields(array $fields): self
    {
        return $this->set(['fields'], $fields);
    }

    /**
     * Set a refresh parameter
     * Accepts: true, false, or 'wait_for'
     */
    public function setRefresh(bool|string $refresh = true): self
    {
        return $this->set(['refresh'], $refresh);
    }

    /**
     * Set conflicts handling
     */
    public function setConflicts(string $conflicts): self
    {
        return $this->set(['conflicts'], $conflicts);
    }

    /**
     * Set routing parameter
     */
    public function setRouting(string $routing): self
    {
        return $this->set(['routing'], $routing);
    }

    /**
     * Add a post_filter
     */
    public function setPostFilter(array $filter): self
    {
        return $this->set(['post_filter'], $filter);
    }

    public function setOpType(string $type): self
    {
        return $this->set(['op_type'], $type);
    }

    public function setOption(array $keys, $value): self
    {
        return $this->set($keys, $value);
    }

    public function loadDsl($dsl): self
    {
        $this->dsl = $dsl;

        return $this;
    }

    public function appendOption(array $keys, $value): self
    {
        $current = $this->getValueAtPath($this->dsl, $keys);
        if ($current) {
            if (empty($current[0])) {
                $current = [$current];
            }
        } else {
            $current = [];
        }
        $current[] = $value;

        return $this->set($keys, $current);
    }

    public function unsetOption(array $keys): self
    {
        $this->unsetKeyAtPath($this->dsl, $keys);

        return $this;
    }

    // ----------------------------------------------------------------------
    // Bulk
    // ----------------------------------------------------------------------

    public function appendBody(array $payload): self
    {
        return $this->path('body')->append($payload);
    }

    // ----------------------------------------------------------------------
    // Internal
    // ----------------------------------------------------------------------

    /**
     * Set a value at the specified path
     */
    protected function set(array $path, $value): self
    {
        $this->setValueAtPath($this->dsl, $path, $value);

        return $this;
    }

    /**
     * Start building a path in the DSL structure
     */
    protected function path(string ...$segments): self
    {
        $this->path = $segments;

        return $this;
    }

    /**
     * Add a segment to the current path
     */
    protected function addPath(string ...$segments): self
    {
        $this->path = array_merge($this->path, $segments);

        return $this;
    }

    /**
     * Set a value at the current path
     */
    protected function value($value): self
    {
        if (empty($this->path)) {
            throw new RuntimeException('No path specified for DSL value');
        }

        $this->setValueAtPath($this->dsl, $this->path, $value);
        $this->path = [];

        return $this;
    }

    /**
     * Append a value to an array at the current path
     */
    protected function append($value): self
    {
        if (empty($this->path)) {
            throw new RuntimeException('No path specified for DSL append');
        }

        $array = $this->getValueAtPath($this->dsl, $this->path) ?? [];
        if (! is_array($array)) {
            throw new RuntimeException('Cannot append to a non-array value');
        }

        $array[] = $value;
        $this->setValueAtPath($this->dsl, $this->path, $array);

        return $this;
    }

    protected function merge(array $value): self
    {
        if (empty($this->path)) {
            throw new RuntimeException('No path specified for DSL merge');
        }

        $current = $this->getValueAtPath($this->dsl, $this->path) ?? [];
        if (! is_array($current)) {
            throw new RuntimeException('Cannot merge with a non-array value');
        }

        $merged = array_merge($current, $value);
        $this->setValueAtPath($this->dsl, $this->path, $merged);

        return $this;
    }
    // ----------------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------------

    protected function unsetKeyAtPath(array &$array, array $path): void
    {
        $current = &$array;

        foreach ($path as $index => $key) {
            if (! isset($current[$key])) {
                return;
            }

            if ($index === array_key_last($path)) {
                unset($current[$key]);

                return;
            }

            $current = &$current[$key];
        }
    }

    protected function setValueAtPath(array &$array, array $path, $value): void
    {
        $current = &$array;

        foreach ($path as $key) {
            if (! isset($current[$key]) || ! is_array($current[$key])) {
                $current[$key] = [];
            }

            $current = &$current[$key];
        }

        $current = $value;
    }

    protected function getValueAtPath(array $array, array $path)
    {
        $current = $array;

        foreach ($path as $key) {
            if (! isset($current[$key])) {
                return null;
            }

            $current = $current[$key];
        }

        return $current;
    }
}

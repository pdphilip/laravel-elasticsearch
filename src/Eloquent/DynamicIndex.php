<?php

namespace PDPhilip\Elasticsearch\Eloquent;

use PDPhilip\Elasticsearch\Exceptions\LogicException;

/**
 * Trait DynamicIndex
 *
 * @mixin Model
 */
trait DynamicIndex
{
    public function __construct()
    {
        parent::__construct();
        $this->setSuffix('*');
    }

    /**
     * Set the table suffix associated with the model.
     *
     * @param  string|null  $suffix
     */
    public function setSuffix($suffix): self
    {
        $this->options()->add('suffix', $suffix);
        $this->meta->setTableSuffix($suffix);

        return $this;
    }

    public function save(array $options = [])
    {
        $validatedSuffix = $this->getMeta()->getTableSuffix() !== '*';
        if (! $validatedSuffix) {
            throw new LogicException('Suffix for Dynamic index must be set before saving');
        }

        return parent::save($options);
    }
}

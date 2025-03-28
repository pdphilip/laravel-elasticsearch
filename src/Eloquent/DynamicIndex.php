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
     */
    public function setSuffix(?string $suffix): self
    {
        $this->options()->add('suffix', $suffix);
        $this->_meta->setTableSuffix($suffix);

        return $this;
    }

    /**
     * Get the table suffix associated with the model.
     */
    public function getSuffix(): string
    {
        return $this->options()->get('suffix', '');
    }

    public function getRecordSuffix(): string
    {
        return $this->getMeta()->getTableSuffix();
    }

    public function save(array $options = [])
    {
        $validatedSuffix = $this->getRecordSuffix() !== '*';
        if (! $validatedSuffix) {
            throw new LogicException('Suffix for Dynamic index must be set before saving');
        }

        return parent::save($options);
    }
}

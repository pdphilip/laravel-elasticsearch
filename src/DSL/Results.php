<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\DSL;

use PDPhilip\Elasticsearch\Meta\QueryMetaData;

class Results
{
    public mixed $data;

    public mixed $errorMessage;

    private QueryMetaData $_meta;

    public function __construct($data, $meta, $params, $queryTag)
    {
        $this->data = $data;
        $this->_meta = new QueryMetaData($meta);
        $this->_meta->setQuery($queryTag);
        $this->_meta->setSuccess();
        $this->_meta->setDsl($params);
        if (! empty($data['_id'])) {
            $this->_meta->setId($data['_id']);
        }
        if (! empty($meta['deleteCount'])) {
            $this->_meta->setDeleted($meta['deleteCount']);
        }
        if (! empty($meta['modified'])) {
            $this->_meta->setModified($meta['modified']);
        }
        if (! empty($meta['failed'])) {
            $this->_meta->setFailed($meta['failed']);
        }
    }

    public function setError($error, $errorCode): void
    {
        $this->_meta->setError($error, $errorCode);
    }

    public function isSuccessful(): bool
    {
        return $this->_meta->isSuccessful();
    }

    public function getMetaData(): QueryMetaData
    {
        return $this->_meta;
    }

    public function getMetaDataAsArray(): array
    {
        return $this->_meta->asArray();
    }

    public function getLogFormattedMetaData(): array
    {
        $return = [];
        $meta = $this->getMetaDataAsArray();
        foreach ($meta as $key => $value) {
            $return['logged_'.$key] = $value;
        }

        return $return;
    }

    public function getInsertedId(): mixed
    {
        return $this->_meta->getId();
    }

    public function getModifiedCount(): int
    {
        return $this->_meta->getModified();
    }

    public function getDeletedCount(): int
    {
        return $this->_meta->getDeleted();
    }
}

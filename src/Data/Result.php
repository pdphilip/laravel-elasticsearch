<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Data;

use Elastic\Elasticsearch\Response\Elasticsearch;
use PDPhilip\Elasticsearch\Meta\QueryMetaData;

class Result
{
    public mixed $result;

    public Elasticsearch $rawResult;

    public mixed $errorMessage;

    private QueryMetaData $_meta;

    public function __construct($result, Elasticsearch $rawResult, array $params)
    {
        $this->result = $result;
        $this->rawResult = $rawResult;
    }

    public function setError($error, $errorCode): void
    {
        $this->_meta->parseAndSetError($error, $errorCode);
    }

    public function getRawResult(): array
    {
        return $this->rawResult->asArray();
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

    public function getTotalCount(): int
    {
        return $this->_meta->getTotal();
    }

    public function getDeletedCount(): int
    {
        return $this->_meta->getDeleted();
    }

    public function __toBoolean(): bool
    {
        return true;
    }
}

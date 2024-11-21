<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Data;

use Elastic\Elasticsearch\Response\Elasticsearch;
use PDPhilip\Elasticsearch\Traits\Makeable;

class Meta
{
    use Makeable;

    protected array $result;

    public function __construct(array $result, array $extra)
    {
        $this->result = [
          ...$result,
          ...$extra
        ];
    }

    public function getTook(): mixed
    {
        return $this->result[''];
    }

    public function getIndex(): string
    {
      return $this->result['_index'];
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
}

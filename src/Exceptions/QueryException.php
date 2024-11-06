<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Exceptions;

use Exception;

class QueryException extends Exception
{
    private array $_details;

    public function __construct($message, $code = 0, ?Exception $previous = null, $details = [])
    {
        parent::__construct($previous->getMessage(), $previous->code, $previous);

        $this->_details = $details;
    }

    public function getDetails(): array
    {
        return $this->_details;
    }
}

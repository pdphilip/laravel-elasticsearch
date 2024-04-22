<?php

namespace PDPhilip\Elasticsearch\DSL\exceptions;

use Exception;


class ParameterException extends Exception
{
    private array $_details = [];
    
    public function __construct($message, $code = 0, Exception $previous = null, $details = [])
    {
        parent::__construct($message, $code, $previous);
        
        $this->_details = $details;
    }
    
    public function getDetails()
    {
        return $this->_details;
    }
}
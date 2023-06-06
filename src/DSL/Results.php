<?php

namespace PDPhilip\Elasticsearch\DSL;

use Elastic\Elasticsearch\Response\Elasticsearch;


class Results
{
    private array $_meta;
    
    public mixed $data;
    public mixed $errorMessage;
    
    
    public function __construct($data, $meta, $params, $queryTag)
    {
        
        if (is_object($meta)) {
            $meta = (array)$meta;
        }
        unset($meta['_source']);
        $this->data = $data;
        $this->_meta = ['query' => $queryTag] + $meta;
        $this->_meta['params'] = $params;
        $this->_meta['_id'] = $data['_id'] ?? null;
        $this->_meta['success'] = true;
        
    }
    
    public function setError($error, $errorCode): void
    {
        
        if ($this->_isJson($error)) {
            
            $jsonError = json_decode($error);
            if (!empty($jsonError->error->reason)) {
                $error = $jsonError->error->reason;
            }
        }
        $this->_meta['error']['msg'] = $error;
        $this->_meta['error']['code'] = $errorCode;
        $this->_meta['success'] = false;
        $this->errorMessage = $error;
        
    }
    
    public function isSuccessful(): bool
    {
        return $this->_meta['success'] ?? false;
    }
    
    public function getMetaData(): array
    {
        return $this->_meta;
    }
    
    public function getLogFormattedMetaData(): array
    {
        $return = [];
        foreach ($this->_meta as $key => $value) {
            $return['logged_'.$key] = $value;
        }
        
        return $return;
    }
    
    public function getInsertedId(): string|null
    {
        return $this->_meta['_id'] ?? null;
    }
    
    
    public function getModifiedCount(): int
    {
        return $this->_meta['modified'] ?? 0;
    }
    
    public function getDeletedCount(): int
    {
        return $this->_meta['deleted'] ?? 0;
    }
    
    private function _isJson($string): bool
    {
        json_decode($string);
        
        return (json_last_error() == JSON_ERROR_NONE);
    }
    
}

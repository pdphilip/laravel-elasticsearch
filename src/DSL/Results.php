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
        unset($meta['_source']);
        unset($meta['hits']);
        unset($meta['aggregations']);
        $this->data = $data;
        $this->_meta = ['query' => $queryTag] + $meta;
        $this->_meta['params'] = $params;
        $this->_meta['_id'] = $data['_id'] ?? null;
        $this->_meta['success'] = true;
        
    }
    
    public function setError($error, $errorCode): void
    {
        $details = $this->_decodeError($error);
        $this->_meta['error']['msg'] = $details['msg'];
        $this->_meta['error']['data'] = $details['data'];
        $this->_meta['error']['code'] = $errorCode;
        $this->_meta['success'] = false;
        $this->errorMessage = $error;
        
    }
    
    private function _decodeError($error)
    {
        $return['msg'] = $error;
        $return['data'] = [];
        $jsonStartPos = strpos($error, ': ') + 2;
        $response = ($error);
        $title = substr($response, 0, $jsonStartPos);
        $jsonString = substr($response, $jsonStartPos);
        $errorArray = json_decode($jsonString, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            $errorReason = $errorArray['error']['reason'] ?? null;
            if (!$errorReason) {
                return $return;
            }
            $return['msg'] = $title.$errorReason;
            $cause = $errorArray['error']['root_cause'][0]['reason'] ?? null;
            if ($cause) {
                $return['msg'] .= ' - '.$cause;
            }
            
            $return['data'] = $errorArray;
            
        }
        
        return $return;
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

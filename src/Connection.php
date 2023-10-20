<?php

namespace PDPhilip\Elasticsearch;

use PDPhilip\Elasticsearch\DSL\Bridge;
use Elasticsearch\ClientBuilder;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;


class Connection extends BaseConnection
{
    
    protected $client;
    protected $index;
    protected $maxSize;
    protected $indexPrefix;
    
    
    public function __construct(array $config)
    {
        $this->config = $config;
        
        if (!empty($config['index_prefix'])) {
            $this->indexPrefix = $config['index_prefix'];
        }
        
        $this->client = $this->buildConnection();
        
        $this->useDefaultPostProcessor();
        
        $this->useDefaultSchemaGrammar();
        
        $this->useDefaultQueryGrammar();
        
    }
    
    public function getIndexPrefix()
    {
        return $this->indexPrefix;
    }
    
    
    public function getTablePrefix()
    {
        return $this->getIndexPrefix();
    }
    
    public function setIndex($index)
    {
        $this->index = $index;
        if ($this->indexPrefix) {
            if (!(strpos($this->index, $this->indexPrefix.'_') !== false)) {
                $this->index = $this->indexPrefix.'_'.$index;
            }
        }
        
        return $this->getIndex();
    }
    
    public function getSchemaGrammar()
    {
        return new Schema\Grammar($this);
    }
    
    public function getIndex()
    {
        return $this->index;
    }
    
    public function setMaxSize($value)
    {
        $this->maxSize = $value;
    }
    
    public function table($table, $as = null)
    {
        return $this->setIndex($table);
    }
    
    
    /**
     * @inheritdoc
     */
    public function getSchemaBuilder()
    {
        return new Schema\Builder($this);
    }
    
    
    /**
     * @inheritdoc
     */
    public function disconnect()
    {
        unset($this->connection);
    }
    
    
    /**
     * @inheritdoc
     */
    public function getDriverName()
    {
        return 'elasticsearch';
    }
    
    /**
     * @inheritdoc
     */
    protected function getDefaultPostProcessor()
    {
        return new Query\Processor();
    }
    
    /**
     * @inheritdoc
     */
    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammar();
    }
    
    /**
     * @inheritdoc
     */
    protected function getDefaultSchemaGrammar()
    {
        return new Schema\Grammar();
    }
    
    
    //----------------------------------------------------------------------
    // Connection Builder
    //----------------------------------------------------------------------
    
    protected function buildConnection()
    {
        $type = config('database.connections.elasticsearch.auth_type') ?? null;
        $type = strtolower($type);
        if (!in_array($type, ['http', 'cloud', 'api'])) {
            throw new RuntimeException('Invalid [auth_type] in database config. Must be: http, cloud or api');
        }
        
        return $this->{'_'.$type.'Connection'}();
        
    }
    
    protected function _httpConnection()
    {
        $hosts = config('database.connections.elasticsearch.hosts') ?? null;
        $username = config('database.connections.elasticsearch.username') ?? null;
        $pass = config('database.connections.elasticsearch.password') ?? null;
        $certPath = config('database.connections.elasticsearch.ssl_cert') ?? null;
        $cb = ClientBuilder::create()->setHosts($hosts);
        if ($username && $pass) {
            $cb->setBasicAuthentication($username, $pass)->build();
        }
        if ($certPath) {
            $cb->setSSLVerification($certPath);
        }
        
        return $cb->build();
    }
    
    protected function _cloudConnection()
    {
        $cloudId = config('database.connections.elasticsearch.cloud_id') ?? null;
        $username = config('database.connections.elasticsearch.username') ?? null;
        $pass = config('database.connections.elasticsearch.password') ?? null;
        $apiId = config('database.connections.elasticsearch.api_id') ?? null;
        $apiKey = config('database.connections.elasticsearch.api_key') ?? null;
        $certPath = config('database.connections.elasticsearch.ssl_cert') ?? null;
        $cb = ClientBuilder::create()->setElasticCloudId($cloudId);
        if ($apiId && $apiKey) {
            $cb->setApiKey($apiId, $apiKey)->build();
        } elseif ($username && $pass) {
            $cb->setBasicAuthentication($username, $pass)->build();
        }
        if ($certPath) {
            $cb->setSSLVerification($certPath);
        }
        
        return $cb->build();
    }
    
    
    protected function _apiConnection()
    {
        $apiId = config('database.connections.elasticsearch.api_id') ?? null;
        $apiKey = config('database.connections.elasticsearch.api_key') ?? null;
        $certPath = config('database.connections.elasticsearch.ssl_cert') ?? null;
        $cb = ClientBuilder::create()->setApiKey($apiId, $apiKey);
        if ($certPath) {
            $cb->setSSLVerification($certPath);
        }
        
        return $cb->build();
    }
    
    
    //----------------------------------------------------------------------
    // Dynamic call routing to DSL bridge
    //----------------------------------------------------------------------
    
    public function __call($method, $parameters)
    {
        $bridge = new Bridge($this->client, $this->index, $this->maxSize);
        
        return $bridge->{'process'.Str::studly($method)}(...$parameters);
    }
}

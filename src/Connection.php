<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\DSL\Bridge;
use PDPhilip\Elasticsearch\DSL\Results;
use RuntimeException;

/**
 * @method bool indexModify(array $settings)
 * @method bool indexCreate(array $settings = [])
 * @method array indexSettings(string $index)
 * @method array getIndices(bool $all = false)
 * @method bool indexExists(string $index)
 * @method bool indexDelete()
 * @method array indexMappings(string $index)
 * @method Results indicesDsl(string $method, array $params)
 * @method Results reIndex(string $from, string $to)
 * @method bool indexAnalyzerSettings(array $settings)
 * @method Results distinctAggregate(string $function, array $wheres, array $options, array $columns)
 * @method Results aggregate(string $function, array $wheres, array $options, array $columns)
 * @method Results distinct(array $wheres, array $options, array $columns, bool $includeDocCount = false)
 * @method Results find(array $wheres, array $options, array $columns)
 * @method Results save(array $data, string $refresh)
 * @method array insertBulk(array $data, bool $returnData = false, string|null $refresh = false)
 * @method Results multipleAggregate(array $functions, array $wheres, array $options, string $column)
 * @method Results deleteAll(array $wheres, array $options = [])
 * @method Results searchRaw(array $bodyParams, bool $returnRaw = false)
 * @method Results aggregationRaw(array $bodyParams)
 * @method Results search(string $searchParams, array $searchOptions, array $wheres, array $options, array $fields, array $columns)
 * @method array toDsl(array $wheres, array $options, array $columns)
 * @method array toDslForSearch(string $searchParams, array $searchOptions, array $wheres, array $options, array $fields, array $columns)
 * @method string openPit(string $keepAlive = '5m')
 * @method bool closePit(string $id)
 * @method Results pitFind(array $wheres, array $options, array $fields, string $pitId, ?array $after, string $keepAlive)
 */
class Connection extends BaseConnection
{
    protected Client $client;

    protected string $index = '';

    protected int $maxSize = 10;

    protected string $indexPrefix = '';

    protected bool $allowIdSort = false;

    protected ?string $errorLoggingIndex = null;

    protected bool $sslVerification = true;

    protected ?int $retires = null; //null will use default

    protected mixed $elasticMetaHeader = null;

    protected bool $rebuild = false;

    protected string $connectionName = 'elasticsearch';

    /**
     * @var Query\Processor
     */
    protected $postProcessor;

    public function __construct(array $config)
    {

        $this->connectionName = $config['name'];

        $this->config = $config;

        $this->setOptions($config);

        $this->client = $this->buildConnection();

        $this->postProcessor = new Query\Processor;

        $this->useDefaultSchemaGrammar();

        $this->useDefaultQueryGrammar();
    }

    public function setOptions($config)
    {
        if (! empty($config['index_prefix'])) {
            $this->indexPrefix = $config['index_prefix'];
        }
        if (isset($config['options']['allow_id_sort'])) {
            $this->allowIdSort = $config['options']['allow_id_sort'];
        }
        if (isset($config['options']['ssl_verification'])) {
            $this->sslVerification = $config['options']['ssl_verification'];
        }
        if (! empty($config['options']['retires'])) {
            $this->retires = $config['options']['retires'];
        }
        if (isset($config['options']['meta_header'])) {
            $this->elasticMetaHeader = $config['options']['meta_header'];
        }
        if (! empty($config['error_log_index'])) {
            if ($this->indexPrefix) {
                $this->errorLoggingIndex = $this->indexPrefix.'_'.$config['error_log_index'];
            } else {
                $this->errorLoggingIndex = $config['error_log_index'];
            }
        }
    }

    protected function buildConnection(): Client
    {
        $type = config('database.connections.elasticsearch.auth_type') ?? null;
        $type = strtolower($type);
        if (! in_array($type, ['http', 'cloud'])) {
            throw new RuntimeException('Invalid [auth_type] in database config. Must be: http, cloud or api');
        }

        return $this->{'_'.$type.'Connection'}();
    }

    public function getTablePrefix(): ?string
    {
        return $this->getIndexPrefix();
    }

    public function getIndexPrefix(): ?string
    {
        return $this->indexPrefix;
    }

    public function getPostProcessor(): Query\Processor
    {
        return $this->postProcessor;
    }

    public function setIndexPrefix($newPrefix): void
    {
        $this->indexPrefix = $newPrefix;
    }

    public function getErrorLoggingIndex(): ?string
    {
        return $this->errorLoggingIndex;
    }

    public function getSchemaGrammar(): Schema\Grammar
    {
        return new Schema\Grammar;
    }

    public function getIndex(): string
    {
        return $this->index;
    }

    public function setIndex($index): string
    {
        $this->index = $index;
        if ($this->indexPrefix) {
            if (! (str_contains($this->index, $this->indexPrefix.'_'))) {
                $this->index = $this->indexPrefix.'_'.$index;
            }
        }

        return $this->getIndex();
    }

    public function table($table, $as = null)
    {
        $query = new Query\Builder($this, new Query\Processor);

        return $query->from($table);
    }

    /**
     * Override the default schema builder.
     */
    public function getSchemaBuilder(): Schema\Builder
    {
        return new Schema\Builder($this);
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): void
    {
        unset($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function getDriverName(): string
    {
        return 'elasticsearch';
    }

    public function rebuildConnection(): void
    {
        $this->rebuild = true;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getMaxSize(): int
    {
        return $this->maxSize;
    }

    public function setMaxSize($value): void
    {
        $this->maxSize = $value;
    }

    public function getAllowIdSort(): bool
    {
        return $this->allowIdSort;
    }

    public function __call($method, $parameters)
    {
        if (! $this->index) {
            $this->index = $this->indexPrefix.'*';
        }
        if ($this->rebuild) {
            $this->client = $this->buildConnection();
            $this->rebuild = false;
        }
        $bridge = new Bridge($this);

        return $bridge->{'process'.Str::studly($method)}(...$parameters);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultPostProcessor(): Query\Processor
    {
        return new Query\Processor;
    }

    //----------------------------------------------------------------------
    // Connection Builder
    //----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    protected function getDefaultQueryGrammar(): Query\Grammar
    {
        return new Query\Grammar;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSchemaGrammar(): Schema\Grammar
    {
        return new Schema\Grammar;
    }

    protected function _httpConnection(): Client
    {
        $hosts = config('database.connections.'.$this->connectionName.'.hosts') ?? null;
        $username = config('database.connections.'.$this->connectionName.'.username') ?? null;
        $pass = config('database.connections.'.$this->connectionName.'.password') ?? null;
        $apiId = config('database.connections.'.$this->connectionName.'.api_id') ?? null;
        $apiKey = config('database.connections.'.$this->connectionName.'.api_key') ?? null;
        $cb = ClientBuilder::create()->setHosts($hosts);
        $cb = $this->_builderOptions($cb);
        if ($username && $pass) {
            $cb->setBasicAuthentication($username, $pass);
        }
        if ($apiKey) {
            $cb->setApiKey($apiKey, $apiId);
        }

        return $cb->build();
    }

    protected function _builderOptions($cb)
    {
        $cb->setSSLVerification($this->sslVerification);
        if (isset($this->elasticMetaHeader)) {
            $cb->setElasticMetaHeader($this->elasticMetaHeader);
        }

        if (isset($this->retires)) {
            $cb->setRetries($this->retires);
        }
        $caBundle = config('database.connections.'.$this->connectionName.'.ssl_cert') ?? null;
        if ($caBundle) {
            $cb->setCABundle($caBundle);
        }
        $sslCert = config('database.connections.'.$this->connectionName.'.ssl.cert') ?? null;
        $sslCertPassword = config('database.connections.'.$this->connectionName.'.ssl.cert_password') ?? null;
        $sslKey = config('database.connections.'.$this->connectionName.'.ssl.key') ?? null;
        $sslKeyPassword = config('database.connections.'.$this->connectionName.'.ssl.key_password') ?? null;
        if ($sslCert) {
            $cb->setSSLCert($sslCert, $sslCertPassword);
        }
        if ($sslKey) {
            $cb->setSSLKey($sslKey, $sslKeyPassword);
        }

        return $cb;
    }

    //----------------------------------------------------------------------
    // Dynamic call routing to DSL bridge
    //----------------------------------------------------------------------

    protected function _cloudConnection(): Client
    {
        $cloudId = config('database.connections.'.$this->connectionName.'.cloud_id') ?? null;
        $username = config('database.connections.'.$this->connectionName.'.username') ?? null;
        $pass = config('database.connections.'.$this->connectionName.'.password') ?? null;
        $apiId = config('database.connections.'.$this->connectionName.'.api_id') ?? null;
        $apiKey = config('database.connections.'.$this->connectionName.'.api_key') ?? null;

        $cb = ClientBuilder::create()->setElasticCloudId($cloudId);
        $cb = $this->_builderOptions($cb);
        if ($username && $pass) {
            $cb->setBasicAuthentication($username, $pass);
        }
        if ($apiKey) {
            $cb->setApiKey($apiKey, $apiId);
        }

        return $cb->build();
    }
}

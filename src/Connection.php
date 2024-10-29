<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\AuthenticationException;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDPhilip\Elasticsearch\DSL\Bridge;
use PDPhilip\Elasticsearch\DSL\Results;
use PDPhilip\Elasticsearch\Enums\WaitFor;
use PDPhilip\Elasticsearch\Exceptions\LogicException;
use RuntimeException;

use function array_replace_recursive;
use function in_array;
use function is_array;
use function strtolower;

/**
 * @method bool indexModify(array $settings)
 * @method bool indexCreate(array $settings = [])
 * @method array indexSettings(string $index)
 * @method array getIndices(bool $all = false)
 * @method bool indexExists(string $index)
 * @method bool indexDelete()
 * @method array indexMappings(string $index)
 * @method array fieldMapping(string $index, string|array $field, bool $raw)
 * @method Results indicesDsl(string $method, array $params)
 * @method Results reIndex(string $from, string $to)
 * @method bool indexAnalyzerSettings(array $settings)
 * @method Results distinctAggregate(string $function, array $wheres, array $options, array $columns)
 * @method Results aggregate(string $function, array $wheres, array $options, array $columns)
 * @method Results distinct(array $wheres, array $options, array $columns, bool $includeDocCount = false)
 * @method Results find(array $wheres, array $options, array $columns)
 * @method Results save(array $data, WaitFor $waitForRefresh = WaitFor::WAITFOR)
 * @method array insertBulk(array $data, bool $returnData = false, WaitFor $waitForRefresh = WaitFor::WAITFOR)
 * @method Results multipleAggregate(array $functions, array $wheres, array $options, string $column)
 * @method Results deleteAll(array $wheres, array $options = [], WaitFor $waitForRefresh = WaitFor::WAITFOR)
 * @method Results searchRaw(array $bodyParams, bool $returnRaw = false)
 * @method Results aggregationRaw(array $bodyParams)
 * @method Results search(string $searchParams, array $searchOptions, array $wheres, array $options, array $fields, array $columns)
 * @method array toDsl(array $wheres, array $options, array $columns)
 * @method array toDslForSearch(string $searchParams, array $searchOptions, array $wheres, array $options, array $fields, array $columns)
 * @method string openPit(string $keepAlive = '5m')
 * @method bool closePit(string $id)
 * @method Results pitFind(array $wheres, array $options, array $fields, string $pitId, ?array $after, string $keepAlive)
 * @method Results getId(string $_id, array $columns = [],$softDeleteColumn = null)
 */
class Connection extends BaseConnection
{
    const VALID_AUTH_TYPES = ['http', 'cloud'];

    /**
     * The Elasticsearch connection handler.
     */
    protected ?Client $client;

    protected string $index = '';

    protected int $maxSize = 10;

    protected string $indexPrefix = '';

    protected bool $allowIdSort = false;

    protected ?string $errorLoggingIndex = null;

    protected bool $sslVerification = true;

    protected ?int $retires = null; //null will use default

    protected mixed $elasticMetaHeader = null;

    protected string $connectionName;

    protected bool $byPassMapValidation = false;

    protected int $insertChunkSize = 1000;

    /**
     * @var Query\Processor
     */
    protected $postProcessor;

    /** {@inheritdoc} */
    public function __construct(array $config)
    {
        $this->connectionName = $config['name'];

        $this->config = $config;

        $this->_sanitizeConfig();

        $this->_validateConnection();

        $this->setOptions();

        $this->client = $this->buildConnection();

        $this->postProcessor = new Query\Processor;

        $this->useDefaultSchemaGrammar();

        $this->useDefaultQueryGrammar();
    }

    public function setOptions(): void
    {
        $this->indexPrefix = $this->config['index_prefix'] ?? '';

        if (isset($this->config['options']['allow_id_sort'])) {
            $this->allowIdSort = $this->config['options']['allow_id_sort'];
        }
        if (isset($this->config['options']['ssl_verification'])) {
            $this->sslVerification = $this->config['options']['ssl_verification'];
        }
        if (! empty($this->config['options']['retires'])) {
            $this->retires = $this->config['options']['retires'];
        }
        if (isset($this->config['options']['meta_header'])) {
            $this->elasticMetaHeader = $this->config['options']['meta_header'];
        }

        if (! empty($this->config['error_log_index'])) {
            $this->errorLoggingIndex = $this->indexPrefix
              ? $this->indexPrefix.'_'.$this->config['error_log_index']
              : $this->config['error_log_index'];
        }

        if (! empty($this->config['options']['bypass_map_validation'])) {
            $this->byPassMapValidation = $this->config['options']['bypass_map_validation'];
        }

        if (! empty($this->config['options']['insert_chunk_size'])) {
            $this->insertChunkSize = $this->config['options']['insert_chunk_size'];
        }

    }

    /** {@inheritdoc} */
    public function table($table, $as = null)
    {
        $query = new Query\Builder($this, new Query\Processor);

        return $query->from($table);
    }

    /** {@inheritdoc} */
    public function disconnect(): void
    {
        $this->client = null;
    }

    //----------------------------------------------------------------------
    // Getters
    //----------------------------------------------------------------------

    /** {@inheritdoc} */
    public function getTablePrefix(): ?string
    {
        return $this->getIndexPrefix();
    }

    public function getIndexPrefix(): ?string
    {
        return $this->indexPrefix;
    }

    /**
     * Retrieves information about the client.
     *
     * @return array An associative array containing the client's information.
     */
    public function getClientInfo(): array
    {
        return $this->client->info()->asArray();
    }

    /** {@inheritdoc} */
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

    /** {@inheritdoc} */
    public function getSchemaGrammar(): Schema\Grammar
    {
        return new Schema\Grammar;
    }

    public function getIndex(): string
    {
        return $this->index;
    }

    /** {@inheritdoc} */
    public function getDriverName(): string
    {
        return 'elasticsearch';
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function getMaxSize(): int
    {
        return $this->maxSize;
    }

    /**
     * Override the default schema builder.
     */
    public function getSchemaBuilder(): Schema\Builder
    {
        return new Schema\Builder($this);
    }

    public function getAllowIdSort(): bool
    {
        return $this->allowIdSort;
    }

    public function getBypassMapValidation(): bool
    {
        return $this->byPassMapValidation;
    }

    public function getInsertChunkSize(): int
    {
        return $this->insertChunkSize;
    }

    /** {@inheritdoc} */
    protected function getDefaultPostProcessor(): Query\Processor
    {
        return new Query\Processor;
    }

    /** {@inheritdoc} */
    protected function getDefaultQueryGrammar(): Query\Grammar
    {
        return new Query\Grammar;
    }

    /** {@inheritdoc} */
    protected function getDefaultSchemaGrammar(): Schema\Grammar
    {
        return new Schema\Grammar;
    }

    //----------------------------------------------------------------------
    // Setters
    //----------------------------------------------------------------------

    public function setIndex(string $index): string
    {
        $this->index = $this->indexPrefix && ! str_contains($index, $this->indexPrefix.'_')
            ? $this->indexPrefix.'_'.$index
            : $index;

        return $this->getIndex();
    }

    public function setMaxSize($value): void
    {
        $this->maxSize = $value;
    }

    public function __call($method, $parameters)
    {
        if (! $this->index) {
            $this->index = $this->indexPrefix.'*';
        }

        // If we are missing a database connection client we need to reconnect.
        if (! $this->client) {
            $this->client = $this->buildConnection();
        }
        $bridge = new Bridge($this);

        $methodName = 'process'.Str::studly($method);

        if (! method_exists($bridge, $methodName)) {
            throw new LogicException("{$methodName} does not exist on the bridge.");
        }

        return $bridge->{$methodName}(...$parameters);
    }

    /**
     * Sanitizes the configuration array by merging it with a predefined array of default configuration settings.
     * This ensures that all required configuration keys exist, even if they are set to null or default values.
     */
    private function _sanitizeConfig(): void
    {

        $this->config = array_replace_recursive(
            [
                'name' => null,
                'auth_type' => '',
                'cloud_id' => null,
                'hosts' => [],
                'username' => null,
                'password' => null,
                'api_key' => null,
                'api_id' => null,
                'index_prefix' => '',
                'options' => [
                    'bypass_map_validation' => false, // This skips the safety checks for Elastic Specific queries.
                    'insert_chunk_size' => 1000, // This is the maximum insert chunk size to use when bulk inserting
                    'logging' => false,
                    'allow_id_sort' => false,
                    'ssl_verification' => true,
                    'retires' => null,
                    'meta_header' => null,
                ],
                'ssl_cert' => null,
                'ssl' => [
                    'key' => null,
                    'key_password' => null,
                    'cert' => null,
                    'cert_password' => null,
                ],
                'error_log_index' => false,
            ],
            $this->config
        );

        $this->config['auth_type'] = strtolower($this->config['auth_type']);

    }

    /**
     * Builds and configures a connection to the ElasticSearch client based on
     * the provided configuration settings.
     *
     * @return Client The configured ElasticSearch client.
     *
     * @throws AuthenticationException
     */
    protected function buildConnection(): Client
    {

        $this->_validateConnection();

        $cb = ClientBuilder::create();

        // Set the connection type
        if ($this->config['auth_type'] === 'http') {
            $cb = $cb->setHosts($this->config['hosts']);
        } else {
            $cb = $cb->setElasticCloudId($this->config['cloud_id']);
        }

        // Set Builder options
        $cb = $this->_builderOptions($cb);

        // Set Authentication
        if ($this->config['username'] && $this->config['password']) {
            $cb->setBasicAuthentication($this->config['username'], $this->config['password']);
        }

        if ($this->config['api_key']) {
            $cb->setApiKey($this->config['api_key'], $this->config['api_id']);
        }

        return $cb->build();
    }

    /**
     * Validates the connection configuration based on the specified authentication type.
     *
     * @throws RuntimeException if the configuration is invalid for the specified authentication type.
     */
    private function _validateConnection(): void
    {
        if (! in_array($this->config['auth_type'], self::VALID_AUTH_TYPES)) {
            throw new RuntimeException('Invalid [auth_type] in database config. Must be: http or cloud');
        }

        if ($this->config['auth_type'] === 'cloud' && ! $this->config['cloud_id']) {
            throw new RuntimeException('auth_type of `cloud` requires `cloud_id` to be set');
        }

        if ($this->config['auth_type'] === 'http' && (! $this->config['hosts'] || ! is_array($this->config['hosts']))) {
            throw new RuntimeException('auth_type of `http` requires `hosts` to be set and be an array');
        }

    }

    /**
     * Configures and returns the client builder with the provided SSL and retry settings.
     *
     * @param  ClientBuilder  $cb  The callback builder instance.
     */
    protected function _builderOptions(ClientBuilder $cb): ClientBuilder
    {
        $cb = $cb->setSSLVerification($this->sslVerification);
        if (isset($this->elasticMetaHeader)) {
            $cb = $cb->setElasticMetaHeader($this->elasticMetaHeader);
        }

        if (isset($this->retires)) {
            $cb = $cb->setRetries($this->retires);
        }

        if ($this->config['options']['logging']) {
            $cb = $cb->setLogger(Log::getLogger());
        }

        if ($this->config['ssl_cert']) {
            $cb = $cb->setCABundle($this->config['ssl_cert']);
        }

        if ($this->config['ssl']['cert']) {
            $cb = $cb->setSSLCert($this->config['ssl']['cert'], $this->config['ssl']['cert_password']);
        }

        if ($this->config['ssl']['key']) {
            $cb = $cb->setSSLKey($this->config['ssl']['key'], $this->config['ssl']['key_password']);
        }

        return $cb;
    }
}

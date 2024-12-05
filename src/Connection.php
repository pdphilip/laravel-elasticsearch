<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\AuthenticationException;
use Elastic\Elasticsearch\Helper\Iterators\SearchHitIterator;
use Elastic\Elasticsearch\Helper\Iterators\SearchResponseIterator;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use PDPhilip\Elasticsearch\Exceptions\BulkInsertQueryException;
use PDPhilip\Elasticsearch\Exceptions\QueryException;
use PDPhilip\Elasticsearch\Query\Builder;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Traits\HasOptions;
use RuntimeException;

use function array_replace_recursive;
use function in_array;
use function is_array;
use function strtolower;

/**
 * @mixin Client
 */
class Connection extends BaseConnection
{
    use HasOptions;

    const VALID_AUTH_TYPES = ['http', 'cloud'];

    /**
     * The Elasticsearch connection handler.
     */
    protected ?Client $connection;

    protected string $connectionName = '';

    protected string $indexSuffix = '';

    /**
     * @var Query\Processor
     */
    protected $postProcessor;

    protected $requestTimeout;

    /** {@inheritdoc} */
    public function __construct(array $config)
    {
        $this->connectionName = $config['name'];

        $this->config = $config;

        $this->sanitizeConfig();

        $this->validateConnection();

        $this->setOptions();

        $this->connection = $this->createConnection();

        $this->postProcessor = new Query\Processor;

        $this->useDefaultSchemaGrammar();

        $this->useDefaultQueryGrammar();
    }

    /**
     * Sanitizes the configuration array by merging it with a predefined array of default configuration settings.
     * This ensures that all required configuration keys exist, even if they are set to null or default values.
     */
    private function sanitizeConfig(): void
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
                'ssl_cert' => null,
                'ssl' => [
                    'key' => null,
                    'key_password' => null,
                    'cert' => null,
                    'cert_password' => null,
                ],
                'options' => [
                  'bypass_map_validation' => false, // This skips the safety checks for Elastic Specific queries.
                  'logging' => false,
                  'ssl_verification' => true,
                  'retires' => null,
                  'meta_header' => null,
                ],
            ],
            $this->config
        );

        $this->config['auth_type'] = strtolower($this->config['auth_type']);

    }

    /**
     * Validates the connection configuration based on the specified authentication type.
     *
     * @throws RuntimeException if the configuration is invalid for the specified authentication type.
     */
    private function validateConnection(): void
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

    public function setOptions(): void
    {
        if (isset($this->config['options']['ssl_verification'])) {
            $this->options()->add('ssl_verification', $this->config['options']['ssl_verification']);
        }

         $this->options()->add('bypass_map_validation', $this->config['options']['bypass_map_validation'] ?? null);

        if (! empty($this->config['options']['retires'])) {
            $this->options()->add('retires', $this->config['options']['retires']);
        }
        if (isset($this->config['options']['meta_header'])) {
            $this->options()->add('meta_header', $this->config['options']['meta_header']);
        }
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function statement($query, $bindings = [], ?Blueprint $blueprint = null)
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            $this->bindValues($query, $this->prepareBindings($bindings));

            $this->recordsHaveBeenModified();

            return $query();
        });
    }

    /**
     * Builds and configures a connection to the ElasticSearch client based on
     * the provided configuration settings.
     *
     * @return Client The configured ElasticSearch client.
     *
     * @throws AuthenticationException
     */
    protected function createConnection(): Client
    {

        $this->validateConnection();

        $clientBuilder = ClientBuilder::create();

        // Set the connection type
        if ($this->config['auth_type'] === 'http') {
            $clientBuilder = $clientBuilder->setHosts($this->config['hosts']);
        } else {
            $clientBuilder = $clientBuilder->setElasticCloudId($this->config['cloud_id']);
        }

        // Set Builder options
        $clientBuilder = $this->builderOptions($clientBuilder);

        // Set Authentication
        if ($this->config['username'] && $this->config['password']) {
            $clientBuilder->setBasicAuthentication($this->config['username'], $this->config['password']);
        }

        if ($this->config['api_key']) {
            $clientBuilder->setApiKey($this->config['api_key'], $this->config['api_id']);
        }

        return $clientBuilder->build();
    }

    /**
     * Configures and returns the client builder with the provided SSL and retry settings.
     *
     * @param  ClientBuilder  $clientBuilder  The callback builder instance.
     */
    protected function builderOptions(ClientBuilder $clientBuilder): ClientBuilder
    {
        $clientBuilder = $clientBuilder->setSSLVerification($this->options()->get('ssl_verification'));
        if ($this->options()->get('meta_header')) {
            $clientBuilder = $clientBuilder->setElasticMetaHeader($this->options()->get('meta_header'));
        }

        $clientBuilder = $clientBuilder->setRetries($this->options()->get('retires', 3));

        if ($this->config['options']['logging']) {
            $clientBuilder = $clientBuilder->setLogger(Log::getLogger());
        }

        if ($this->config['ssl_cert']) {
            $clientBuilder = $clientBuilder->setCABundle($this->config['ssl_cert']);
        }

        if ($this->config['ssl']['cert']) {
            $clientBuilder = $clientBuilder->setSSLCert($this->config['ssl']['cert'], $this->config['ssl']['cert_password']);
        }

        if ($this->config['ssl']['key']) {
            $clientBuilder = $clientBuilder->setSSLKey($this->config['ssl']['key'], $this->config['ssl']['key_password']);
        }

        return $clientBuilder;
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->connection, $method], $parameters);
    }

    public function createAlias(string $index, string $name): void {
        $this->indices()->putAlias(compact('index', 'name'));
    }

    public function createIndex(string $index, array $body): void
    {
        try {
            $this->indices()->create(compact('index', 'body'));
        } catch (\Exception $e) {
            throw new QueryException($e);
        }
    }

    /**
     * Run a select statement against the database and return a generator.
     *
     * @param  array  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return \Generator
     */
    public function searchResponseIterator($query, $scrollTimeout = '30s', $size = 100) {

        $scrollParams = [
            'scroll' => $scrollTimeout,
            'size' => $size, // Number of results per shard
            'index' => $query['index'],
            'body' => $query['body'],
        ];

        $pages = new SearchResponseIterator($this->connection, $scrollParams);
        foreach ($pages as $page) {
            yield $page;
        }

    }

    /**
     * Run a select statement against the database and return a generator.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return \Generator
     */
    public function cursor($query, $bindings = [], $useReadPdo = false, $scrollTimeout = '30s')
    {

        $limit = $query['body']['size'] ?? null;

        //We want to scroll by 1000 row chunks
        $query['body']['size'] = 1000;

        $scrollParams = [
            'scroll' => $scrollTimeout,
            'index' => $query['index'],
            'body' => $query['body'],
        ];

        $count = 0;
        $pages = new SearchResponseIterator($this->connection, $scrollParams);
        $hits = new SearchHitIterator($pages);
        foreach ($hits as $hit) {
            $count++;
            if ($count > $limit) {
                return;
            }
            yield $hit;
        }
    }

    /** {@inheritdoc} */
    public function disconnect(): void
    {
        $this->connection = null;
    }

    public function dropIndex(string $index): void
    {
        $this->indices()->delete(compact('index'));
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return array
     */
    public function delete($query, $bindings = [])
    {
        return $this->run(
            $query,
            $bindings,
            $this->connection->deleteByQuery(...)
        );
    }

    public function getClient(): ?Client
    {
        return $this->connection;
    }

    /** {@inheritdoc} */
    public function getDriverName(): string
    {
        return 'elasticsearch';
    }

    /**
     * @return Schema\Builder
     */
    public function getSchemaBuilder()
    {
        return new Schema\Builder($this);
    }

    /**
     * @return Schema\Grammars\Grammar
     */
    public function getSchemaGrammar()
    {
        return new Schema\Grammars\Grammar;
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  array  $params
     * @param  array  $bindings
     *
     * @throws BulkInsertQueryException
     */
    public function insert($params, $bindings = []): Elasticsearch
    {
        $result = $this->run(
            $this->addClientParams($params),
            $bindings,
            $this->connection->bulk(...)
        );

        if (! empty($result['errors'])) {
            throw new BulkInsertQueryException($result);
        }

        return $result;
    }

    /**
     * Add client-specific parameters to the request params
     */
    protected function addClientParams(array $params): array
    {
        if ($this->requestTimeout) {
            $params['client']['timeout'] = $this->requestTimeout;
        }

        return $params;
    }

    /**
     * Log a query in the connection's query log.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  float|null  $time
     * @return void
     */
    public function logQuery($query, $bindings, $time = null)
    {
        $this->event(new QueryExecuted($query, $bindings, $time, $this));

        if ($this->loggingQueries) {
            $this->queryLog[] = compact('query', 'bindings', 'time');
        }
    }

    /**
     * Prepare the query bindings for execution.
     *
     *
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        return $bindings;
    }

    /**
     * Get a new query builder instance.
     */
    public function query()
    {
        return new Builder(
            $this, $this->getQueryGrammar(), $this->getPostProcessor()
        );
    }

    public function reconnectIfMissingConnection()
    {
        if (is_null($this->connection)) {
            $this->createConnection();
        }
    }

    /**
     * Run a select statement against the database.
     *
     * @param  array  $params
     * @param  array  $bindings
     * @return array
     */
    public function select($params, $bindings = [], $useReadPdo = true)
    {
        return $this->run(
            $this->addClientParams($params),
            $bindings,
            $this->connection->search(...)
        );
    }

    /**
     * Set the table prefix in use by the connection.
     *
     * @param  string  $prefix
     * @return void
     */
    public function setIndexSuffix($suffix)
    {
        $this->indexSuffix = $suffix;

        $this->getQueryGrammar()->setIndexSuffix($suffix);
    }

    /**
     * Get the timeout for the entire Elasticsearch request
     *
     * @param  float  $requestTimeout  seconds
     */
    public function setRequestTimeout(float $requestTimeout): self
    {
        $this->requestTimeout = $requestTimeout;

        return $this;
    }

    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return array
     */
    public function update($query, $bindings = [])
    {
        $updateMethod = isset($query['body']['query']) ? 'updateByQuery' : 'update';

        return $this->run(
            $query,
            $bindings,
            $this->connection->$updateMethod(...)
        );
    }

    /**
     * Run a reindex statement against the database.
     *
     * @param  string|array  $query
     * @param  array  $bindings
     * @return array
     */
    public function reindex($query, $bindings = [])
    {
        return $this->run(
            $query,
            $bindings,
            $this->connection->reindex(...)
        );
    }

    public function updateIndex(string $index, array $body): void
    {
        $this->indices()->putMapping(compact('index', 'body'));
    }

    public function getIndexPrefix(): string
    {
        return $this->config['index_prefix'];
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
    protected function getDefaultSchemaGrammar(): Schema\Grammars\Grammar
    {
        return new Schema\Grammars\Grammar;
    }

    /** {@inheritdoc} */
    protected function runQueryCallback($query, $bindings, \Closure $callback)
    {
        try {
            $result = $callback($query, $bindings);
        } catch (\Exception $e) {
            throw new QueryException($e);
        }

        return $result;
    }
}

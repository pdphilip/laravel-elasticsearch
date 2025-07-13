<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch;

use Closure;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Endpoints\Indices;
use Elastic\Elasticsearch\Exception\AuthenticationException;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Helper\Iterators\SearchHitIterator;
use Elastic\Elasticsearch\Helper\Iterators\SearchResponseIterator;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Exception;
use Generator;
use Http\Promise\Promise;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use PDPhilip\Elasticsearch\Exceptions\BulkInsertQueryException;
use PDPhilip\Elasticsearch\Exceptions\QueryException;
use PDPhilip\Elasticsearch\Laravel\Compatibility\Connection\ConnectionCompatibility;
use PDPhilip\Elasticsearch\Query\Builder;
use PDPhilip\Elasticsearch\Query\Processor;
use PDPhilip\Elasticsearch\Schema\Blueprint;
use PDPhilip\Elasticsearch\Traits\HasOptions;
use RuntimeException;

use function array_replace_recursive;
use function in_array;
use function is_array;
use function strtolower;

/**
 * @mixin Client
 *
 * @method Processor getPostProcessor()
 */
class Connection extends BaseConnection
{
    use ConnectionCompatibility;
    use HasOptions;

    const VALID_AUTH_TYPES = ['http', 'cloud'];

    /**
     * The Elasticsearch connection handler.
     */
    protected ?ElasticClient $connection;

    protected string $connectionName = '';

    /**
     * @var Query\Processor
     */
    protected $postProcessor;

    protected $requestTimeout;

    public $allowIdSort = false;

    public $defaultQueryLimit = 1000;

    /** {@inheritdoc}
     * @throws AuthenticationException
     */
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

        if (! empty($this->config['index_prefix'])) {
            $this->setIndexPrefix($this->config['index_prefix']);
        }
    }

    // ----------------------------------------------------------------------
    // Connection Setup
    // ----------------------------------------------------------------------

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
                    'default_limit' => null,
                    'allow_id_sort' => false,
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
        $this->allowIdSort = $this->config['options']['allow_id_sort'] ?? false;

        $this->options()->add('bypass_map_validation', $this->config['options']['bypass_map_validation'] ?? null);

        if (isset($this->config['options']['ssl_verification'])) {
            $this->options()->add('ssl_verification', $this->config['options']['ssl_verification']);
        }

        if (! empty($this->config['options']['retires'])) {
            $this->options()->add('retires', (int) $this->config['options']['retires']);
        }

        if (isset($this->config['options']['meta_header'])) {
            $this->options()->add('meta_header', $this->config['options']['meta_header']);
        }

        if (isset($this->config['options']['default_limit'])) {
            $this->defaultQueryLimit = (int) $this->config['options']['default_limit'];
        }
    }

    /**
     * Builds and configures a connection to the ElasticSearch client based on
     * the provided configuration settings.
     *
     * @return ElasticClient The configured ElasticSearch client.
     *
     * @throws AuthenticationException
     */
    protected function createConnection(): ElasticClient
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

        return new ElasticClient($clientBuilder->build());
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

    /** {@inheritdoc} */
    public function disconnect(): void
    {
        $this->connection = $this->createConnection();
    }

    // ----------------------------------------------------------------------
    // Connection getters
    // ----------------------------------------------------------------------
    public function getClient(): ?ElasticClient
    {
        return $this->connection;
    }

    public function getIndexPrefix(): string
    {
        return $this->getTablePrefix();
    }

    /**
     * Get the client info
     *
     * @throws ClientResponseException|ServerResponseException
     */
    public function getClientInfo(): array
    {
        return $this->elastic()->info()->asArray();
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function getLicenseInfo(): array
    {
        $license = $this->elastic()->license()->get()->asArray();
        if (! empty($license['license'])) {
            return $license['license'];
        }

        return $license;
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function getLicenseType(): ?string
    {
        $license = $this->getLicenseInfo();
        if (! empty($license['type'])) {
            return $license['type'];
        }

        return null;
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

    /** {@inheritdoc} */
    protected function getDefaultPostProcessor(): Query\Processor
    {
        return new Query\Processor;
    }

    public function getDefaultLimit(): int
    {
        return $this->defaultQueryLimit;
    }

    // ----------------------------------------------------------------------
    // Connection Setters
    // ----------------------------------------------------------------------

    public function setIndexPrefix($prefix): self
    {
        return $this->setTablePrefix($prefix);
    }

    /**
     * Set the timeout for the entire Elasticsearch request
     *
     * @param  float  $requestTimeout  seconds
     */
    public function setRequestTimeout(float $requestTimeout): self
    {
        $this->requestTimeout = $requestTimeout;

        return $this;
    }

    // ----------------------------------------------------------------------
    // Schema Management
    // ----------------------------------------------------------------------

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function createAlias(string $index, string $name): void
    {
        $this->connection->createAlias($index, $name);
    }

    /**
     * @throws QueryException
     */
    public function createIndex(string $index, array $body): array
    {
        try {
            $this->connection->createIndex($index, $body);

            return $this->connection->getMappings($index);
        } catch (Exception $e) {
            throw new QueryException($e, compact('index', 'body'));
        }
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function dropIndex(string $index): void
    {
        $this->connection->dropIndex($index);
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function updateIndex(string $index, array $body): array
    {
        $this->connection->updateIndex($index, $body);

        return $this->connection->getMappings($index);
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function getFieldMapping($index, $fields): array
    {
        return $this->connection->getFieldMapping($index, $fields);
    }

    public function getMappings($index): array
    {
        return $this->connection->getMappings($index);
    }

    public function indices(): Indices
    {
        return $this->connection->indices();
    }

    // ----------------------------------------------------------------------
    // Query Execution
    // ----------------------------------------------------------------------

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array  $bindings
     */
    public function statement($query, $bindings = [], ?Blueprint $blueprint = null): bool
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
     * Run a select statement against the database and return a generator.
     *
     * @param  array  $query
     * @param  string  $scrollTimeout
     * @param  int  $size
     */
    public function searchResponseIterator($query, $scrollTimeout = '30s', $size = 100): Generator
    {

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
     * @param  array  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @param  string  $scrollTimeout
     */
    public function cursor($query, $bindings = [], $useReadPdo = false, $scrollTimeout = '30s')
    {

        $limit = is_array($query) && isset($query['body']['size']) ? $query['body']['size'] : null;

        // We want to scroll by 1000 row chunks
        $query['body']['size'] = 1000;

        $scrollParams = [
            'scroll' => $scrollTimeout,
            'index' => $query['index'],
            'body' => $query['body'],
        ];

        $count = 0;
        $pages = new SearchResponseIterator($this->elastic(), $scrollParams);
        $hits = new SearchHitIterator($pages);

        foreach ($hits as $hit) {
            $count++;
            if ($count > $limit) {
                break;
            }
            yield $hit;
        }

        return (function () {
            yield;
        })();
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

    /**
     * Run an insert statement against the database.
     *
     * @param  array  $query
     * @param  array  $bindings
     *
     * @throws BulkInsertQueryException
     */
    public function insert($query, $bindings = [], $continueWithErrors = false): Elasticsearch
    {
        $result = $this->run(
            $this->addClientParams($query),
            $bindings,
            $this->connection->bulk(...)
        );

        if (! $continueWithErrors && ! empty($result['errors'])) {
            throw new BulkInsertQueryException($result);
        }

        return $result;
    }

    /**
     * Log a query in the connection's query log.
     *
     * @param  string|array  $query
     * @param  array  $bindings
     * @param  float|null  $time
     */
    public function logQuery($query, $bindings, $time = null): void
    {
        if (is_array($query)) {
            $query = json_encode($query);
        }

        $this->event(new QueryExecuted($query, $bindings, $time, $this));

        if ($this->loggingQueries) {
            $this->queryLog[] = compact('query', 'bindings', 'time');
        }
    }

    /**
     * Prepare the query bindings for execution.
     */
    public function prepareBindings(array $bindings): array
    {
        return $bindings;
    }

    /**
     * Get a new query builder instance.
     */
    public function query(): Builder
    {
        return new Builder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    /**
     * @throws AuthenticationException
     */
    public function reconnectIfMissingConnection(): void
    {
        if (is_null($this->connection)) {
            $this->connection = $this->createConnection();
        }
    }

    /**
     * Run a select statement against the database.
     *
     * @param  array  $params
     * @param  array  $bindings
     */
    public function select($params, $bindings = [], $useReadPdo = true): Elasticsearch
    {
        return $this->run(
            $this->addClientParams($params),
            $bindings,
            $this->connection->search(...)
        );
    }

    public function count($params): int
    {
        return $this->connection->count($params);
    }

    /**
     * Run an update statement against the database.
     *
     * @param  array  $query
     * @param  array  $bindings
     */
    public function update($query, $bindings = []): mixed
    {
        $updateMethod = isset($query['body']['query']) ? 'updateByQuery' : 'update';

        return $this->run(
            $query,
            $bindings,
            $this->connection->$updateMethod(...)
        );
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function raw($value): Elasticsearch|Promise
    {
        return $this->connection->search($value);
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

    /** {@inheritdoc}
     * @throws QueryException
     */
    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        try {
            $result = $callback($query, $bindings);
        } catch (Exception $e) {
            throw new QueryException($e, $query);
        }

        return $result;
    }

    /**
     * @param  mixed  $query
     */
    protected function run($query, $bindings, Closure $callback): mixed
    {
        return parent::run($query, $bindings, $callback);
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function openPit(mixed $query): ?string
    {
        return $this->connection->openPit($query);
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function closePit(mixed $query): bool
    {
        return $this->connection->closePit($query);
    }

    // ----------------------------------------------------------------------
    // Direct Client Access and cluster methods
    // ----------------------------------------------------------------------

    public function elastic(): Client
    {
        return $this->connection->client();
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function clusterSettings($flat = true): array
    {
        return $this->connection->clusterSettings($flat);
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function setClusterFieldDataOnId(bool $enabled, bool $transient = false): array
    {
        return $this->connection->setClusterFieldDataOnId($enabled, $transient);
    }

    // ----------------------------------------------------------------------
    // Call Catch
    // ----------------------------------------------------------------------

    /**
     * Dynamically pass methods to the connection.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    //    public function __call($method, $parameters)
    //    {
    //        dd($method);
    //
    //        return call_user_func_array([$this->connection, $method], $parameters);
    //    }

    // ----------------------------------------------------------------------
    // Later/Maybe
    // ----------------------------------------------------------------------

    //    /**
    //     * Run a reindex statement against the database.
    //     *
    //     * @param  string|array  $query
    //     * @param  array  $bindings
    //     * @return array
    //     */
    //    public function reindex($query, $bindings = [])
    //    {
    //        return $this->run(
    //            $query,
    //            $bindings,
    //            $this->connection->reindex(...)
    //        )->asArray();
    //    }
}

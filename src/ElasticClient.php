<?php

namespace PDPhilip\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Endpoints\Indices;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Helper\Iterators\SearchHitIterator;
use Elastic\Elasticsearch\Helper\Iterators\SearchResponseIterator;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Http\Promise\Promise;
use Illuminate\Support\Arr;
use PDPhilip\Elasticsearch\Query\DSL\DslBuilder;

class ElasticClient
{
    public function __construct(protected Client $client) {}

    public function client(): Client
    {
        return $this->client;
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function search(array $params = []): Elasticsearch|Promise
    {
        return $this->client->search($params);
    }

    public function count(array $params = []): int
    {
        return $this->client->count($params)->asArray()['count'] ?? 0;
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function bulk(array $params = []): Elasticsearch|Promise
    {
        return $this->client->bulk($params);
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     */
    public function update(array $params = []): Elasticsearch|Promise
    {
        return $this->client->update($params);
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function updateByQuery(array $params = []): Elasticsearch|Promise
    {
        return $this->client->updateByQuery($params);
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function deleteByQuery(array $params = []): Elasticsearch|Promise
    {
        return $this->client->deleteByQuery($params);
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function scroll(array $params = []): Elasticsearch|Promise
    {
        return $this->client->scroll($params);
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function clearScroll(array $params = []): Elasticsearch|Promise
    {
        return $this->client->clearScroll($params);
    }

    // ----------------------------------------------------------------------
    // Indices
    // ----------------------------------------------------------------------

    public function indices(): Indices
    {
        return $this->client->indices();
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function getMappings(string|array $index): array
    {
        $params = ['index' => Arr::wrap($index)];

        return $this->client->indices()->getMapping($params)->asArray();
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function createAlias(string $index, string $name): Elasticsearch|Promise
    {
        return $this->client->indices()->putAlias(compact('index', 'name'));
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function createIndex(string $index, array $body): Elasticsearch|Promise
    {
        return $this->client->indices()->create(compact('index', 'body'));
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function dropIndex(string $index): Elasticsearch|Promise
    {
        return $this->client->indices()->delete(compact('index'));
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function updateIndex(string $index, array $body): void
    {
        if ($mappings = $body['mappings'] ?? null) {
            $this->client->indices()->putMapping(['index' => $index, 'body' => ['properties' => $mappings['properties']]]);
        }
        if ($settings = $body['settings'] ?? null) {
            $this->client->indices()->close(['index' => $index]);
            $this->client->indices()->putSettings(['index' => $index, 'body' => ['settings' => $settings]]);
            $this->client->indices()->open(['index' => $index]);
        }
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     */
    public function getFieldMapping(string $index, string $fields): array
    {
        return $this->client->indices()->getFieldMapping(compact('index', 'fields'))->asArray();
    }

    // ----------------------------------------------------------------------
    // PIT API
    // ----------------------------------------------------------------------

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     */
    public function openPit(array $params = []): ?string
    {
        $open = $this->client->openPointInTime($params)->asArray();

        return $open['id'] ?? null;
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function closePit(array $params = []): bool
    {
        $closed = $this->client->closePointInTime($params)->asArray();

        return $closed['succeeded'] ?? false;
    }

    // ----------------------------------------------------------------------
    // Cluster
    // ----------------------------------------------------------------------

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function clusterSettings($flat = true): array
    {
        return $this->client->cluster()->getSettings(['flat_settings' => (bool) $flat])->asArray();
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function setClusterFieldDataOnId($enabled, $transient = false): array
    {
        $type = $transient ? 'transient' : 'persistent';
        $dsl = new DslBuilder;
        $dsl->setBody([$type, 'indices.id_field_data.enabled'], (bool) $enabled);

        return $this->client->cluster()->putSettings($dsl->getDsl())->asArray();

    }

    // ----------------------------------------------------------------------
    // Index Information
    // ----------------------------------------------------------------------

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function catIndices(array $params = []): array
    {
        return $this->client->cat()->indices($params)->asArray();
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     */
    public function indexExists(string $index): bool
    {
        return $this->client->indices()->exists(['index' => $index])->asBool();
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function getIndex(string|array $index): array
    {
        return $this->client->indices()->get(['index' => $index])->asArray();
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function getIndexSettings(string|array $index): array
    {
        return $this->client->indices()->getSettings(['index' => Arr::wrap($index)])->asArray();
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function reindex(array $params): array
    {
        return $this->client->reindex($params)->asArray();
    }

    // ----------------------------------------------------------------------
    // Server Info
    // ----------------------------------------------------------------------

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function info(): array
    {
        return $this->client->info()->asArray();
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function getLicense(): array
    {
        return $this->client->license()->get()->asArray();
    }

    // ----------------------------------------------------------------------
    // Iterators
    // ----------------------------------------------------------------------

    public function createSearchResponseIterator(array $scrollParams): SearchResponseIterator
    {
        return new SearchResponseIterator($this->client, $scrollParams);
    }

    public function createSearchHitIterator(SearchResponseIterator $responseIterator): SearchHitIterator
    {
        return new SearchHitIterator($responseIterator);
    }
}

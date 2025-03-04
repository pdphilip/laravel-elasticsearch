<?php

namespace PDPhilip\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Illuminate\Support\Arr;

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
    public function search(array $params = [])
    {
        return $this->client->search($params);
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function bulk(array $params = [])
    {
        return $this->client->bulk($params);
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     * @throws MissingParameterException
     */
    public function update(array $params = [])
    {
        return $this->client->update($params);
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function updateByQuery(array $params = [])
    {
        return $this->client->updateByQuery($params);
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function deleteByQuery(array $params = [])
    {
        return $this->client->deleteByQuery($params);
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function scroll(array $params = [])
    {
        return $this->client->scroll($params);
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function clearScroll(array $params = [])
    {
        return $this->client->clearScroll($params);
    }

    // ----------------------------------------------------------------------
    // Indices
    // ----------------------------------------------------------------------

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function getMappings(string $index): array
    {
        $params = ['index' => Arr::wrap($index)];

        return $this->client->indices()->getMapping($params)->asArray();
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function createAlias(string $index, string $name)
    {
        $this->client->indices()->putAlias(compact('index', 'name'));
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function createIndex(string $index, array $body)
    {
        $this->client->indices()->create(compact('index', 'body'));
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function dropIndex(string $index)
    {
        $this->client->indices()->delete(compact('index'));
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function updateIndex(string $index, array $body)
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
}

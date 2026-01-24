<?php

namespace App\Services;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use RuntimeException;

/**
 * Elasticsearch Service
 *
 * Service for interacting with Elasticsearch with automatic index prefixing.
 * Provides methods for indexing, searching, and managing documents.
 */
class ElasticsearchService
{
    /**
     * The prefix to be used for all Elasticsearch indices.
     *
     * @var string
     */
    protected string $indexPrefix;

    /**
     * Elasticsearch client instance.
     *
     * @var Client
     */
    protected Client $client;

    /**
     * Initialize ElasticsearchService with configured prefix.
     */
    public function __construct()
    {
        $this->indexPrefix = config('elasticsearch.index_prefix', 'laravel');
        $this->client = $this->buildClient();
    }

    /**
     * Build Elasticsearch client.
     *
     * @return Client
     */
    protected function buildClient(): Client
    {
        $config = config('elasticsearch.connection', []);
        
        $builder = ClientBuilder::create();

        if (isset($config['host'])) {
            $host = $config['host'];
            if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
                $builder->setHosts([$host]);
            } else {
                $scheme = $config['scheme'] ?? 'http';
                $port = $config['port'] ?? 9200;
                $builder->setHosts(["{$scheme}://{$host}:{$port}"]);
            }
        } else {
            $builder->setHosts([config('elasticsearch.host', 'http://127.0.0.1:9200')]);
        }

        if (isset($config['user']) && isset($config['pass'])) {
            $builder->setBasicAuthentication($config['user'], $config['pass']);
        }

        return $builder->build();
    }

    /**
     * Build prefixed index name.
     *
     * @param string $index
     * @return string
     */
    protected function getIndexName(string $index): string
    {
        return "{$this->indexPrefix}_{$index}";
    }

    /**
     * Index a document (create or update).
     *
     * @param string $index Index name (without prefix)
     * @param string|int $id Document ID
     * @param array $body Document data
     * @return array
     */
    public function index(string $index, string|int $id, array $body): array
    {
        $params = [
            'index' => $this->getIndexName($index),
            'id' => $id,
            'body' => $body,
        ];

        $response = $this->client->index($params);
        return $response->asArray();
    }

    /**
     * Get a document by ID.
     *
     * @param string $index Index name (without prefix)
     * @param string|int $id Document ID
     * @return array|null
     */
    public function get(string $index, string|int $id): ?array
    {
        try {
            $params = [
                'index' => $this->getIndexName($index),
                'id' => $id,
            ];

            $response = $this->client->get($params);
            return $response->asArray()['_source'] ?? null;
        } catch (ClientResponseException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Get a document by ID or throw exception if not found.
     *
     * @param string $index Index name (without prefix)
     * @param string|int $id Document ID
     * @return array
     * @throws RuntimeException
     */
    public function getOrFail(string $index, string|int $id): array
    {
        $document = $this->get($index, $id);

        if ($document === null) {
            throw new RuntimeException("Elasticsearch document not found: {$index}/{$id}");
        }

        return $document;
    }

    /**
     * Search documents.
     *
     * @param string $index Index name (without prefix)
     * @param array $query Search query
     * @param array $options Additional options (size, from, sort, etc.)
     * @return array
     */
    public function search(string $index, array $query = [], array $options = []): array
    {
        $params = [
            'index' => $this->getIndexName($index),
            'body' => [
                'query' => $query ?: ['match_all' => new \stdClass()],
            ],
        ];

        if (isset($options['size'])) {
            $params['size'] = $options['size'];
        }

        if (isset($options['from'])) {
            $params['from'] = $options['from'];
        }

        if (isset($options['sort'])) {
            $params['body']['sort'] = $options['sort'];
        }

        $response = $this->client->search($params);
        $data = $response->asArray();

        return [
            'total' => $data['hits']['total']['value'] ?? 0,
            'hits' => array_map(function ($hit) {
                return [
                    'id' => $hit['_id'],
                    'score' => $hit['_score'] ?? null,
                    'source' => $hit['_source'] ?? [],
                ];
            }, $data['hits']['hits'] ?? []),
        ];
    }

    /**
     * Delete a document by ID.
     *
     * @param string $index Index name (without prefix)
     * @param string|int $id Document ID
     * @return bool
     */
    public function delete(string $index, string|int $id): bool
    {
        try {
            $params = [
                'index' => $this->getIndexName($index),
                'id' => $id,
            ];

            $response = $this->client->delete($params);
            $data = $response->asArray();
            return ($data['result'] ?? '') === 'deleted';
        } catch (ClientResponseException $e) {
            if ($e->getCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Check if a document exists.
     *
     * @param string $index Index name (without prefix)
     * @param string|int $id Document ID
     * @return bool
     */
    public function exists(string $index, string|int $id): bool
    {
        try {
            $params = [
                'index' => $this->getIndexName($index),
                'id' => $id,
            ];

            $response = $this->client->exists($params);
            return $response->asBool();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create an index.
     *
     * @param string $index Index name (without prefix)
     * @param array $settings Index settings and mappings
     * @return array
     */
    public function createIndex(string $index, array $settings = []): array
    {
        $params = [
            'index' => $this->getIndexName($index),
        ];

        if (!empty($settings)) {
            $params['body'] = $settings;
        }

        $response = $this->client->indices()->create($params);
        return $response->asArray();
    }

    /**
     * Delete an index.
     *
     * @param string $index Index name (without prefix)
     * @return bool
     */
    public function deleteIndex(string $index): bool
    {
        try {
            $params = [
                'index' => $this->getIndexName($index),
            ];

            $response = $this->client->indices()->delete($params);
            $data = $response->asArray();
            return ($data['acknowledged'] ?? false) === true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if an index exists.
     *
     * @param string $index Index name (without prefix)
     * @return bool
     */
    public function indexExists(string $index): bool
    {
        try {
            $params = [
                'index' => $this->getIndexName($index),
            ];

            $response = $this->client->indices()->exists($params);
            return $response->asBool();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Bulk index documents.
     *
     * @param string $index Index name (without prefix)
     * @param array $documents Array of ['id' => '...', 'body' => [...]]
     * @return array
     */
    public function bulk(string $index, array $documents): array
    {
        $params = ['body' => []];

        foreach ($documents as $document) {
            $params['body'][] = [
                'index' => [
                    '_index' => $this->getIndexName($index),
                    '_id' => $document['id'] ?? null,
                ],
            ];

            $params['body'][] = $document['body'] ?? [];
        }

        $response = $this->client->bulk($params);
        return $response->asArray();
    }
}


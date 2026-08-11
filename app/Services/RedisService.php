<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use RuntimeException;

/**
 * Redis Service
 *
 * Service for interacting with Redis cache with automatic JSON encoding/decoding.
 * All keys are automatically prefixed.
 */
class RedisService
{
    /**
     * The prefix to be used for all Redis keys.
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Initialize RedisService with configured prefix.
     */
    public function __construct()
    {
        $this->prefix = config('redis.key_prefix', 'laravel');
    }

    /**
     * Build prefixed Redis key.
     *
     * @param string $key
     * @return string
     */
    protected function key(string $key): string
    {
        return "{$this->prefix}:{$key}";
    }

    /**
     * Store a value in Redis.
     *
     * @param string $key
     * @param mixed $value Arrays are automatically JSON encoded
     * @param int|null $ttl Time to live in seconds (null = no expiration)
     * @return void
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $data = is_array($value) ? json_encode($value) : $value;

        $ttl
            ? Redis::setex($this->key($key), $ttl, $data)
            : Redis::set($this->key($key), $data);
    }

    /**
     * Retrieve a value from Redis.
     *
     * @param string $key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Automatically decodes JSON to arrays
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = Redis::get($this->key($key));

        if ($value === null) {
            return $default;
        }

        $json = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $json : $value;
    }

    /**
     * Retrieve a value from Redis or throw exception if not found.
     *
     * @param string $key
     * @return mixed
     * @throws RuntimeException
     */
    public function getOrFail(string $key): mixed
    {
        $value = Redis::get($this->key($key));

        if ($value === null) {
            throw new RuntimeException("Redis key not found: {$key}");
        }

        $json = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $json : $value;
    }

    /**
     * Delete a key from Redis.
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        return (bool) Redis::del($this->key($key));
    }

    /**
     * Check if a key exists in Redis.
     *
     * @param string $key
     * @return bool
     */
    public function exists(string $key): bool
    {
        return (bool) Redis::exists($this->key($key));
    }

    /**
     * Get cached value or execute callback and cache the result.
     *
     * @param string $key
     * @param int $ttl Time to live in seconds
     * @param \Closure $callback Executed only if key doesn't exist
     * @return mixed
     */
    public function remember(string $key, int $ttl, \Closure $callback): mixed
    {
        if ($this->exists($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }
}


<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Redis Connection Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection settings for Redis. These settings
    | are used when connecting to Redis for caching, sessions, and queues.
    |
    */

    'client' => env('REDIS_CLIENT', 'phpredis'),
    'key_prefix' => env('REDIS_KEY_PREFIX', 'laravel'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', ''),
        'persistent' => env('REDIS_PERSISTENT', false),
    ],

    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
        'max_retries' => env('REDIS_MAX_RETRIES', 3),
        'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
        'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
        'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
    ],

    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
        'max_retries' => env('REDIS_MAX_RETRIES', 3),
        'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
        'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
        'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
    ],

];


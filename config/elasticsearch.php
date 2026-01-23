<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Connection Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection settings for Elasticsearch.
    | These settings are used when connecting to Elasticsearch for search
    | operations.
    |
    */

    'host' => env('ELASTICSEARCH_HOST', 'http://127.0.0.1:9200'),
    
    'connection' => [
        'host' => env('ELASTICSEARCH_HOST', 'http://127.0.0.1:9200'),
        'port' => env('ELASTICSEARCH_PORT', 9200),
        'scheme' => env('ELASTICSEARCH_SCHEME', 'http'),
        'user' => env('ELASTICSEARCH_USER'),
        'pass' => env('ELASTICSEARCH_PASS'),
    ],

    'index_prefix' => env('ELASTICSEARCH_INDEX_PREFIX', 'laravel'),

];


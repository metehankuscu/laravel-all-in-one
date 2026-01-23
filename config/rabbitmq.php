<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Connection Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection settings for RabbitMQ message
    | broker. These settings are used when connecting to RabbitMQ for
    | queue operations.
    |
    */

    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Management Interface
    |--------------------------------------------------------------------------
    |
    | Configuration for RabbitMQ Management UI access.
    |
    */

    'management' => [
        'host' => env('RABBITMQ_MANAGEMENT_HOST', '127.0.0.1'),
        'port' => env('RABBITMQ_MANAGEMENT_PORT', 15672),
    ],

];


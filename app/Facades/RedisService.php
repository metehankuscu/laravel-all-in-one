<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class RedisService extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'redis-service';
    }
}


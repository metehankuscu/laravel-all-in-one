<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * ElasticsearchService Facade
 *
 * @method static array index(string $index, string|int $id, array $body)
 * @method static array|null get(string $index, string|int $id)
 * @method static array getOrFail(string $index, string|int $id)
 * @method static array search(string $index, array $query = [], array $options = [])
 * @method static bool delete(string $index, string|int $id)
 * @method static bool exists(string $index, string|int $id)
 * @method static array createIndex(string $index, array $settings = [])
 * @method static bool deleteIndex(string $index)
 * @method static bool indexExists(string $index)
 * @method static array bulk(string $index, array $documents)
 */
class ElasticsearchService extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'elasticsearch-service';
    }
}


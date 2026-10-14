<?php

namespace Vinkius\ConnectLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method \Vinkius\ConnectLaravel\UserContext user(string $externalId)
 * @method \Vinkius\ConnectLaravel\Resources\CatalogResource catalog()
 * @method \Vinkius\ConnectLaravel\Resources\AppUsersResource users()
 * @method \Vinkius\ConnectLaravel\Http\RuntimeClient runtime(string $mcpUrl)
 * @method \Vinkius\ConnectLaravel\Support\ResolverCache cache()
 * @method string call(string $connector, string $name)
 *
 * @see \Vinkius\ConnectLaravel\Vinkius
 */
class Vinkius extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Vinkius\ConnectLaravel\Vinkius::class;
    }
}

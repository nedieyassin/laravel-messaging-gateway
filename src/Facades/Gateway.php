<?php

namespace Nedieyassin\MessagingGateway\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Nedieyassin\MessagingGateway\GatewayClient;
use Nedieyassin\MessagingGateway\GatewayCredentials;
use Nedieyassin\MessagingGateway\GatewayManager;

/**
 * @method static void resolveCredentialsUsing(?Closure $callback)
 * @method static GatewayClient using(GatewayCredentials|string|array $credentials, ?string $url = null)
 * @method static mixed withCredentials(GatewayCredentials|string|array $credentials, Closure $callback)
 *
 * @mixin GatewayClient
 */
class Gateway extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GatewayManager::class;
    }
}
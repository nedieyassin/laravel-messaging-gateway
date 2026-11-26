<?php

namespace Nedieyassin\MessagingGateway\Facades;

use Illuminate\Support\Facades\Facade;
use Nedieyassin\MessagingGateway\GatewayClient;

/** @mixin GatewayClient */
class Gateway extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GatewayClient::class;
    }
}
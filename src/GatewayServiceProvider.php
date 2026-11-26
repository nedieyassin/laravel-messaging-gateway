<?php

namespace Nedieyassin\MessagingGateway;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use Nedieyassin\MessagingGateway\Enums\Channel;

class GatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/gateway.php', 'gateway');

        $this->app->singleton(GatewayClient::class, fn() => new GatewayClient(
            url: (string)config('gateway.url'),
            token: (string)config('gateway.token'),
            timeout: (int)config('gateway.timeout'),
            retries: (int)config('gateway.retries'),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/gateway.php' => config_path('gateway.php')], 'gateway-config');
        }

        // gateway_whatsapp, gateway_sms, gateway_email, gateway_push become valid names for via()
        $this->callAfterResolving(ChannelManager::class, function (ChannelManager $manager) {
            foreach (Channel::cases() as $channel) {
                $manager->extend($channel->driver(), fn($app) => $app->make($channel->channelClass()));
            }
        });
    }
}
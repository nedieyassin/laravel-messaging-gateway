<?php

namespace Nedieyassin\MessagingGateway;

use Closure;
use Illuminate\Notifications\Notification;
use Nedieyassin\MessagingGateway\Exceptions\GatewayException;

/**
 * Picks the credentials for each call. Behind the Gateway facade, so Gateway::sendSms() etc. use the
 * same resolution as the notification channels.
 *
 * Order: notification, notifiable, an active withCredentials() scope, the global resolver, the config default.
 */
final class GatewayManager
{
    private ?Closure $resolver = null;

    /** @var list<GatewayCredentials> */
    private array $scope = [];

    public function __construct(private readonly GatewayClient $default)
    {
    }

    /**
     * Called for every send. Receives the notifiable and the notification (both null for direct
     * Gateway:: calls) and returns a token string, an array, GatewayCredentials or null.
     */
    public function resolveCredentialsUsing(?Closure $callback): void
    {
        $this->resolver = $callback;
    }

    /** A client bound to these credentials: Gateway::using($tenant->gateway_token)->sendSms(...) */
    public function using(GatewayCredentials|string|array $credentials, ?string $url = null): GatewayClient
    {
        $credentials = is_string($credentials) && $url ? new GatewayCredentials($credentials, $url) : GatewayCredentials::from($credentials);

        return $this->default->for($credentials);
    }

    /** Everything inside the callback (notify() calls included) sends with these credentials. */
    public function withCredentials(GatewayCredentials|string|array $credentials, Closure $callback): mixed
    {
        $this->scope[] = GatewayCredentials::from($credentials);

        try {
            return $callback();
        } finally {
            array_pop($this->scope);
        }
    }

    public function client(?object $notifiable = null, ?Notification $notification = null): GatewayClient
    {
        $credentials = $this->fromNotification($notification, $notifiable)
            ?? $this->fromNotifiable($notifiable)
            ?? ($this->scope ? $this->scope[array_key_last($this->scope)] : null)
            ?? ($this->resolver ? GatewayCredentials::tryFrom(($this->resolver)($notifiable, $notification)) : null);

        if ($credentials !== null) {
            return $this->default->for($credentials);
        }
        if (!config('gateway.use_default_credentials', true)) {
            throw GatewayException::noCredentials();
        }

        return $this->default;
    }

    private function fromNotification(?Notification $notification, ?object $notifiable): ?GatewayCredentials
    {
        return $notification && method_exists($notification, 'gatewayCredentials')
            ? GatewayCredentials::tryFrom($notification->gatewayCredentials($notifiable))
            : null;
    }

    private function fromNotifiable(?object $notifiable): ?GatewayCredentials
    {
        return $notifiable && method_exists($notifiable, 'gatewayCredentials')
            ? GatewayCredentials::tryFrom($notifiable->gatewayCredentials())
            : null;
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->client()->{$method}(...$arguments);
    }
}
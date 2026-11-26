<?php // Channels/GatewayChannel.php

namespace Nedieyassin\MessagingGateway\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use LogicException;
use Nedieyassin\MessagingGateway\Enums\Channel;
use Nedieyassin\MessagingGateway\GatewayClient;

abstract readonly class GatewayChannel
{
    public function __construct(protected GatewayClient $client)
    {
    }

    abstract protected function channel(): Channel;

    /** @return list<string> the addresses the notifiable has for this channel */
    protected function routes(mixed $notifiable, Notification $notification): array
    {
        if (!method_exists($notifiable, 'routeNotificationFor')) {
            return [];
        }

        $route = $notifiable->routeNotificationFor($this->channel()->driver(), $notification);
        $values = array_map('trim', array_filter(Arr::wrap($route), 'is_string'));
        return array_values(array_unique(array_filter($values, fn(string $v) => $v !== '')));
    }

    protected function build(mixed $notifiable, Notification $notification, string $method): mixed
    {
        if (!method_exists($notification, $method)) {
            throw new LogicException(sprintf(
                '%s must define %s() to be sent through the %s channel.',
                $notification::class, $method, $this->channel()->value,
            ));
        }

        return $notification->{$method}($notifiable);
    }

    /** The tenant tag can come from the notifiable when the message doesn't set one. */
    protected function withDefaults(object $message, mixed $notifiable): object
    {
        if ($message->tenantId === null && method_exists($notifiable, 'gatewayTenantId')) {
            $message->tenant($notifiable->gatewayTenantId());
        }

        return $message;
    }

    /**
     * One key per notification, channel and recipient: a queue retry (or a retried HTTP call) gives the
     * gateway the same key and it returns the original message instead of sending again.
     */
    protected function key(Notification $notification, string $address, ?int $part = null): ?string
    {
        if (!config('gateway.idempotency', true) || empty($notification->id)) {
            return null;
        }

        return implode(':', array_filter(
            [$notification->id, $this->channel()->value, substr(sha1($address), 0, 12), $part],
            fn($v) => $v !== null,
        ));
    }
}
<?php // Concerns/UsesGatewayChannels.php

namespace Nedieyassin\MessagingGateway\Concerns;

use Illuminate\Support\Arr;
use Nedieyassin\MessagingGateway\Enums\Channel;

/** Add to notifications that choose their channels with gatewayVia(). */
trait UsesGatewayChannels
{
    /**
     * Only the channels this notifiable can actually be reached on.
     *
     * With $firstOnly the first channel that has an address wins, which gives you a preference order
     * (push, else WhatsApp, else SMS). This is based on *having* an address, not on delivery: the
     * gateway doesn't fall back on failures.
     *
     * @param list<Channel> $channels
     * @return list<string>
     */
    protected function gatewayVia(object $notifiable, array $channels, bool $firstOnly = false): array
    {
        $via = [];

        foreach ($channels as $channel) {
            $route = method_exists($notifiable, 'routeNotificationFor')
                ? $notifiable->routeNotificationFor($channel->driver(), $this)
                : null;

            if (array_filter(Arr::wrap($route), fn($v) => is_string($v) && trim($v) !== '') === []) {
                continue;
            }

            $via[] = $channel->driver();
            if ($firstOnly) {
                break;
            }
        }

        return $via;
    }
}
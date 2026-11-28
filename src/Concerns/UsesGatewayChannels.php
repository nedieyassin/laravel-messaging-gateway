<?php

namespace Nedieyassin\MessagingGateway\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Nedieyassin\MessagingGateway\Enums\Channel;
use Nedieyassin\MessagingGateway\GatewayCredentials;

/** Add to notifications that choose their channels with gatewayVia() and/or need their own gateway token. */
trait UsesGatewayChannels
{
    /**
     * Credentials set with usingGatewayCredentials(), kept encrypted: a queued notification is serialized
     * into the queue, and a plain token would be readable there.
     */
    protected ?string $gatewayCredentialsPayload = null;

    // ----- channels -----

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

    // ----- credentials -----

    /**
     * Send this notification with a specific gateway token (and optionally a different gateway URL):
     *
     *   $guardian->notify((new FeeReminder($invoice))->usingGatewayCredentials($school->gateway_token));
     *
     * The token is stored encrypted with APP_KEY, which web and queue workers share. It is fixed at the
     * moment you call this. If the token must be looked up when the notification is actually sent
     * (for example because tenants can rotate it), override resolveGatewayCredentials() instead.
     */
    public function usingGatewayCredentials(
        #[\SensitiveParameter] GatewayCredentials|string|array $credentials,
    ): static
    {
        $credentials = GatewayCredentials::from($credentials);

        $this->gatewayCredentialsPayload = Crypt::encryptString(
            json_encode(['token' => $credentials->token, 'url' => $credentials->url], JSON_THROW_ON_ERROR)
        );

        return $this;
    }

    /** Called by the package for every send. Credentials set with usingGatewayCredentials() win.
     * @throws \JsonException
     */
    public function gatewayCredentials(object $notifiable): GatewayCredentials|string|null
    {
        if ($this->gatewayCredentialsPayload !== null) {
            $data = json_decode(Crypt::decryptString($this->gatewayCredentialsPayload), true, flags: JSON_THROW_ON_ERROR);
            return new GatewayCredentials($data['token'], $data['url'] ?? null);
        }
        return $this->resolveGatewayCredentials($notifiable);
    }

    /**
     * Override to look the token up when the notification is sent (inside the queue worker, with the tenant
     * restored). Return a token, ['token' => ..., 'url' => ...], GatewayCredentials, or null to fall
     * through to the notifiable, the global resolver and the .env default.
     */
    protected function resolveGatewayCredentials(object $notifiable): GatewayCredentials|string|array|null
    {
        return null;
    }
}
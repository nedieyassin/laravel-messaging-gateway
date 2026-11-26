<?php // Concerns/ReceivesGatewayNotifications.php

namespace Nedieyassin\MessagingGateway\Concerns;

use Illuminate\Support\Arr;

/**
 * Add to any notifiable model (next to Notifiable). Resolves where to send from config('gateway.resolvers'),
 * and every resolver can be overridden by defining the method on the model itself.
 */
trait ReceivesGatewayNotifications
{
    // ----- routing: what Laravel calls for gateway_* channels -----

    public function routeNotificationForGatewayEmail(): ?string
    {
        return $this->gatewayEmail();
    }

    public function routeNotificationForGatewaySms(): ?string
    {
        return $this->gatewayPhone();
    }

    public function routeNotificationForGatewayWhatsapp(): ?string
    {
        return $this->gatewayWhatsAppNumber();
    }

    /** @return list<string> */
    public function routeNotificationForGatewayPush(): array
    {
        return $this->gatewayFcmTokens();
    }

    // ----- resolvers: override any of these on the model -----

    public function gatewayEmail(): ?string
    {
        foreach ($this->gatewayCandidates('email') as $value) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        return null;
    }

    /** The first value that looks like a phone number. The gateway normalises 0999..., +265999... and 265999.... */
    public function gatewayPhone(): ?string
    {
        foreach ($this->gatewayCandidates('phone') as $value) {
            $digits = strlen(preg_replace('/\D+/', '', $value));
            if ($digits >= 8 && $digits <= 15) {
                return $value;
            }
        }

        return null;
    }

    public function gatewayWhatsAppNumber(): ?string
    {
        foreach ($this->gatewayCandidates('whatsapp') as $value) {
            $digits = strlen(preg_replace('/\D+/', '', $value));
            if ($digits >= 8 && $digits <= 15) {
                return $value;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function gatewayFcmTokens(): array
    {
        return $this->gatewayCandidates('push');
    }

    /** The tenant_id the gateway tags this notifiable's messages with (for example the school). */
    public function gatewayTenantId(): ?string
    {
        $attribute = config('gateway.resolvers.tenant');
        $value = $attribute ? data_get($this, $attribute) : null;
        return $value === null || $value === '' ? null : (string)$value;
    }

    /** @return list<string> */
    protected function gatewayCandidates(string $kind): array
    {
        $found = [];

        foreach ((array)config("gateway.resolvers.{$kind}", []) as $path) {
            $value = data_get($this, $path);
            if (is_string($value) && str_starts_with(ltrim($value), '[')) {
                $value = json_decode($value, true) ?? $value;   // a JSON column that isn't cast
            }
            foreach (Arr::flatten(Arr::wrap($value)) as $item) {
                if (is_scalar($item) && trim((string)$item) !== '') {
                    $found[] = trim((string)$item);
                }
            }
        }

        return array_values(array_unique($found));
    }
}
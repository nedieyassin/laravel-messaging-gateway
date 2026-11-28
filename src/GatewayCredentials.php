<?php

namespace Nedieyassin\MessagingGateway;

use InvalidArgumentException;
use LogicException;

/** A gateway token (and optionally a different gateway URL) for one client. */
final class GatewayCredentials
{
    public function __construct(
        #[\SensitiveParameter] public readonly string $token,
        public readonly ?string                       $url = null,
    )
    {
        if (trim($token) === '') {
            throw new InvalidArgumentException('The gateway token is empty.');
        }
    }

    /** For explicit input: an empty token is a mistake and throws. */
    public static function from(self|string|array $value): self
    {
        return match (true) {
            $value instanceof self => $value,
            is_array($value) => new self((string)($value['token'] ?? ''), $value['url'] ?? null),
            default => new self($value),
        };
    }

    /** For resolver results: null, '' or a missing token mean "no credentials here". */
    public static function tryFrom(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }
        if (is_array($value)) {
            $value = ($value['token'] ?? '') === '' ? null : $value;
        }
        if ($value === null || $value === '' || !(is_string($value) || is_array($value))) {
            return null;
        }

        return self::from($value);
    }

    public function __debugInfo(): array
    {
        return ['token' => '****' . substr($this->token, -4), 'url' => $this->url];
    }

    /** Queued notifications are serialized: a token must be resolved when the job runs, never stored in it. */
    public function __serialize(): array
    {
        throw new LogicException(
            'GatewayCredentials must not be serialized. Return them from gatewayCredentials() or ' .
            'Gateway::resolveCredentialsUsing() so they are resolved when the notification is sent.'
        );
    }
}
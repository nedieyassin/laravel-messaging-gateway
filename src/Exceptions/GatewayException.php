<?php

namespace Nedieyassin\MessagingGateway\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class GatewayException extends RuntimeException
{
    public function __construct(
        string                  $message,
        public readonly ?int    $status = null,
        public readonly ?string $errorCode = null,
        public readonly array   $detail = [],
        ?Throwable              $previous = null,
    )
    {
        parent::__construct($message, $status ?? 0, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $detail = $response->json('detail');
        $code = null;
        $message = "Gateway answered HTTP {$response->status()}";

        if (is_array($detail) && isset($detail['code'])) {          // {"detail": {"code", "message"}}
            $code = (string)$detail['code'];
            $message = (string)($detail['message'] ?? $message);
        } elseif (is_string($detail)) {                              // {"detail": "Invalid or missing token"}
            $message = $detail;
        } elseif (is_array($detail) && $detail !== []) {             // FastAPI validation errors
            $code = 'VALIDATION_ERROR';
            $first = $detail[0] ?? [];
            $message = trim(implode('.', (array)($first['loc'] ?? [])) . ': ' . ($first['msg'] ?? 'invalid request'), ': ');
        }

        return new self($message, $response->status(), $code, is_array($detail) ? $detail : []);
    }

    public static function connection(Throwable $e): self
    {
        return new self('Could not reach the gateway: ' . $e->getMessage(), null, 'CONNECTION', [], $e);
    }

    public static function noCredentials(): self
    {
        return new self(
            'No gateway credentials were resolved. Set GATEWAY_TOKEN, or register ' .
            'Gateway::resolveCredentialsUsing(), or define gatewayCredentials() on the notifiable.',
            null, 'NO_CREDENTIALS',
        );
    }

    public function retryable(): bool
    {
        if ($this->errorCode === 'NO_CREDENTIALS') {
            return false;   // retrying can't fix a missing token
        }

        return $this->status === null || $this->status === 429 || $this->status >= 500;
    }

    /** WhatsApp free text outside the 24-hour window: send a template instead. */
    public function outsideWindow(): bool
    {
        return $this->errorCode === 'OUTSIDE_WINDOW';
    }
}
<?php // Messages/Concerns/HasContext.php

namespace Nedieyassin\MessagingGateway\Messages\Concerns;

/** Tags the gateway stores with the message: used for filtering, reporting and billing per tenant. */
trait HasContext
{
    public ?string $tenantId = null;

    /** @var array<string, string> */
    public array $refs = [];

    public ?string $idempotencyKey = null;

    public ?string $recipientName = null;

    public function tenant(string|int|null $id): static
    {
        $this->tenantId = $id === null ? null : (string)$id;
        return $this;
    }

    /** Up to 10 tags; keys like "student" or "invoice", values up to 128 characters. */
    public function refs(array $refs): static
    {
        foreach ($refs as $key => $value) {
            $this->refs[(string)$key] = (string)$value;
        }
        return $this;
    }

    public function ref(string $key, string|int $value): static
    {
        return $this->refs([$key => $value]);
    }

    /** Overrides the key the channel generates. */
    public function idempotencyKey(string $key): static
    {
        $this->idempotencyKey = $key;
        return $this;
    }

    public function recipientName(?string $name): static
    {
        $this->recipientName = $name;
        return $this;
    }
}
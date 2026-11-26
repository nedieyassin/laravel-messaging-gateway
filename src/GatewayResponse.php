<?php

namespace Nedieyassin\MessagingGateway;

use Nedieyassin\MessagingGateway\Enums\Channel;

/** What the channels return, so it arrives as $event->response in NotificationSent listeners. */
final class GatewayResponse
{
    /** @param list<string> $ids gateway message ids (one per recipient or device token) */
    public function __construct(public readonly Channel $channel, public readonly array $ids)
    {
    }

    public function id(): ?string
    {
        return $this->ids[0] ?? null;
    }

    /** @param list<self> $responses */
    public static function merge(Channel $channel, array $responses): self
    {
        return new self($channel, array_merge(...array_map(fn(self $r) => $r->ids, $responses)));
    }
}
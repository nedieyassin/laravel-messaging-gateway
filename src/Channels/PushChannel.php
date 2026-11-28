<?php // Channels/PushChannel.php

namespace Nedieyassin\MessagingGateway\Channels;

use Illuminate\Notifications\Notification;
use Nedieyassin\MessagingGateway\Enums\Channel;
use Nedieyassin\MessagingGateway\GatewayResponse;

readonly class PushChannel extends GatewayChannel
{
    private const CHUNK = 500; // the gateway accepts up to 500 tokens per request

    protected function channel(): Channel
    {
        return Channel::PUSH;
    }

    public function send(mixed $notifiable, Notification $notification): ?GatewayResponse
    {

        if (!$tokens = $this->routes($notifiable, $notification)) {
            return null;
        }

        $client = $this->client($notifiable, $notification);

        $message = $this->withDefaults($this->build($notifiable, $notification, 'toGatewayPush'), $notifiable);
        $base = $this->key($notification, implode(',', $tokens));

        $responses = [];
        foreach (array_chunk($tokens, self::CHUNK) as $i => $chunk) {
            $responses[] = $client->sendPush($chunk, $message, $base ? "{$base}:{$i}" : null);
        }

        return GatewayResponse::merge(Channel::PUSH, $responses);
    }
}
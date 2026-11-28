<?php // Channels/WhatsAppChannel.php

namespace Nedieyassin\MessagingGateway\Channels;

use Illuminate\Notifications\Notification;
use Nedieyassin\MessagingGateway\Enums\Channel;
use Nedieyassin\MessagingGateway\GatewayResponse;

readonly class WhatsAppChannel extends GatewayChannel
{
    protected function channel(): Channel
    {
        return Channel::WHATSAPP;
    }

    public function send(mixed $notifiable, Notification $notification): ?GatewayResponse
    {
        if (!$numbers = $this->routes($notifiable, $notification)) {
            return null;
        }

        $client = $this->client($notifiable, $notification);


        $message = $this->withDefaults($this->build($notifiable, $notification, 'toGatewayWhatsApp'), $notifiable);
        return GatewayResponse::merge(Channel::WHATSAPP, array_map(
        /**
         * @throws \JsonException
         */ fn(string $number) => $client->sendWhatsApp($number, $message, $this->key($notification, $number)),
            $numbers,
        ));
    }
}
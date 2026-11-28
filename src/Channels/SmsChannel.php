<?php // Channels/SmsChannel.php

namespace Nedieyassin\MessagingGateway\Channels;

use Illuminate\Notifications\Notification;
use Nedieyassin\MessagingGateway\Enums\Channel;
use Nedieyassin\MessagingGateway\GatewayResponse;
use Nedieyassin\MessagingGateway\Messages\SmsMessage;

readonly class SmsChannel extends GatewayChannel
{
    protected function channel(): Channel
    {
        return Channel::SMS;
    }

    public function send(mixed $notifiable, Notification $notification): ?GatewayResponse
    {


        if (!$numbers = $this->routes($notifiable, $notification)) {
            return null;
        }

        $client = $this->client($notifiable, $notification);

        $message = $this->build($notifiable, $notification, 'toGatewaySms');
        $message = $message instanceof SmsMessage ? $message : SmsMessage::make((string)$message);
        $this->withDefaults($message, $notifiable);
        if (count($numbers) === 1) {
            return $client->sendSms($numbers[0], $message, $this->key($notification, $numbers[0]));
        }
        return $client->sendSmsBulk($numbers, $message, $this->key($notification, implode(',', $numbers)));
    }
}
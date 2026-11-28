<?php // Channels/EmailChannel.php

namespace Nedieyassin\MessagingGateway\Channels;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use LogicException;
use Nedieyassin\MessagingGateway\Enums\Channel;
use Nedieyassin\MessagingGateway\GatewayResponse;
use Nedieyassin\MessagingGateway\Messages\EmailMessage;

readonly class EmailChannel extends GatewayChannel
{
    protected function channel(): Channel
    {
        return Channel::EMAIL;
    }

    public function send(mixed $notifiable, Notification $notification): ?GatewayResponse
    {
        if (!$addresses = $this->routes($notifiable, $notification)) {
            return null;
        }


        $client = $this->client($notifiable, $notification);


        $message = $this->withDefaults($this->message($notifiable, $notification), $notifiable);
        return GatewayResponse::merge(Channel::EMAIL, array_map(
        /**
         * @throws \JsonException
         */ fn(string $to) => $client->sendEmail($to, $message, $this->key($notification, $to)),
            $addresses,
        ));
    }

    /** toGatewayEmail() wins; otherwise an existing toMail() MailMessage is reused. */
    private function message(mixed $notifiable, Notification $notification): EmailMessage
    {
        if (method_exists($notification, 'toGatewayEmail')) {
            return $notification->toGatewayEmail($notifiable);
        }

        if (method_exists($notification, 'toMail')) {
            $mail = $notification->toMail($notifiable);
            if ($mail instanceof MailMessage) {
                return EmailMessage::fromMailMessage($mail, Str::title(Str::snake(class_basename($notification), ' ')));
            }
        }

        throw new LogicException(sprintf(
            '%s must define toGatewayEmail(), or toMail() returning a MailMessage.', $notification::class,
        ));
    }
}
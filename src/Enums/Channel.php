<?php // Enums/Channel.php

namespace Nedieyassin\MessagingGateway\Enums;

use Nedieyassin\MessagingGateway\Channels\EmailChannel;
use Nedieyassin\MessagingGateway\Channels\PushChannel;
use Nedieyassin\MessagingGateway\Channels\SmsChannel;
use Nedieyassin\MessagingGateway\Channels\WhatsAppChannel;

enum Channel: string
{
    case WHATSAPP = 'whatsapp';
    case SMS = 'sms';
    case EMAIL = 'email';
    case PUSH = 'push';

    /** The name to return from a notification's via(). */
    public function driver(): string
    {
        return 'gateway_' . $this->value;
    }

    /** @return class-string<\Nedieyassin\MessagingGateway\Channels\GatewayChannel> */
    public function channelClass(): string
    {
        return match ($this) {
            self::WHATSAPP => WhatsAppChannel::class,
            self::SMS => SmsChannel::class,
            self::EMAIL => EmailChannel::class,
            self::PUSH => PushChannel::class,
        };
    }
}
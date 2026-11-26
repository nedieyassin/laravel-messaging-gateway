<?php // Enums/MessageStatus.php

namespace Nedieyassin\MessagingGateway\Enums;

/** Status of a message in the gateway, for Gateway::message($id) lookups. */
enum MessageStatus: string
{
    case RECEIVED = 'RECEIVED';
    case QUEUED = 'QUEUED';
    case SENDING = 'SENDING';
    case SENT = 'SENT';
    case DELIVERED = 'DELIVERED';
    case READ = 'READ';
    case FAILED = 'FAILED';

    public function failed(): bool
    {
        return $this === self::FAILED;
    }

    /** Still on its way: QUEUED or SENDING. SENT is the end for email and push. */
    public function pending(): bool
    {
        return in_array($this, [self::QUEUED, self::SENDING], true);
    }
}
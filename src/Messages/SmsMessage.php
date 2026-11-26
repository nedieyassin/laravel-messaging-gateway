<?php // Messages/SmsMessage.php

namespace Nedieyassin\MessagingGateway\Messages;

use Carbon\CarbonInterface;
use Nedieyassin\MessagingGateway\Messages\Concerns\HasContext;

final class SmsMessage
{
    use HasContext;

    public bool $priority = false;

    public ?CarbonInterface $scheduledAt = null;

    public function __construct(public string $text)
    {
    }

    public static function make(string $text): SmsMessage
    {
        return new SmsMessage($text);
    }

    /** Skip the batch window (use for OTPs). */
    public function priority(bool $priority = true): SmsMessage
    {
        $this->priority = $priority;
        return $this;
    }

    public function later(CarbonInterface $when): SmsMessage
    {
        $this->scheduledAt = $when;
        return $this;
    }
}
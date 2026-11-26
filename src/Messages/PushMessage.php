<?php // Messages/PushMessage.php

namespace Nedieyassin\MessagingGateway\Messages;

use Carbon\CarbonInterface;
use Nedieyassin\MessagingGateway\Messages\Concerns\HasContext;

final class PushMessage
{
    use HasContext;

    /** @var array<string, string> */
    public array $data = [];

    public ?string $image = null;

    public ?CarbonInterface $scheduledAt = null;

    public function __construct(public ?string $title, public string $body)
    {
    }

    public static function make(?string $title, string $body): PushMessage
    {
        return new PushMessage($title, $body);
    }

    /** Custom payload for the app, for example ['screen' => 'reports']. */
    public function data(array $data): PushMessage
    {
        foreach ($data as $key => $value) {
            $this->data[(string)$key] = (string)$value;
        }
        return $this;
    }

    public function image(string $url): PushMessage
    {
        $this->image = $url;
        return $this;
    }

    public function later(CarbonInterface $when): PushMessage
    {
        $this->scheduledAt = $when;
        return $this;
    }
}
<?php // Messages/EmailMessage.php

namespace Nedieyassin\MessagingGateway\Messages;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Arr;
use Nedieyassin\MessagingGateway\Messages\Concerns\HasContext;
use Nedieyassin\MessagingGateway\Support\File;

final class EmailMessage
{
    use HasContext;

    public ?string $text = null;

    public ?string $html = null;

    /** @var list<string> */
    public array $cc = [];

    /** @var list<string> */
    public array $bcc = [];

    public ?string $replyTo = null;

    /** @var list<File> */
    public array $files = [];

    public function __construct(public string $subject)
    {
    }

    public static function make(string $subject): static
    {
        return new EmailMessage($subject);
    }

    public function text(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function html(string $html): static
    {
        $this->html = $html;
        return $this;
    }

    public function view(string $view, array $data = []): static
    {
        $this->html = view($view, $data)->render();
        return $this;
    }

    public function cc(string|array $addresses): static
    {
        $this->cc = [...$this->cc, ...Arr::wrap($addresses)];
        return $this;
    }

    public function bcc(string|array $addresses): static
    {
        $this->bcc = [...$this->bcc, ...Arr::wrap($addresses)];

        return $this;
    }

    public function replyTo(string $address): static
    {
        $this->replyTo = $address;

        return $this;
    }

    public function attach(string $path, ?string $as = null): static
    {
        $this->files[] = File::fromPath($path, $as);
        return $this;
    }

    public function attachData(string $data, string $name): static
    {
        $this->files[] = File::fromData($data, $name);
        return $this;
    }

    public function attachFromStorage(string $disk, string $path, ?string $as = null): static
    {
        $this->files[] = File::fromStorage($disk, $path, $as);
        return $this;
    }

    /** Lets an existing toMail() notification go through the gateway. Attachment objects are not supported. */
    public static function fromMailMessage(MailMessage $mail, string $fallbackSubject = 'Notification'): EmailMessage
    {
        $message = new EmailMessage($mail->subject ?: $fallbackSubject);
        $message->html = (string)$mail->render();
        $message->cc = array_column($mail->cc, 0);
        $message->bcc = array_column($mail->bcc, 0);
        $message->replyTo = $mail->replyTo[0][0] ?? null;

        foreach ($mail->attachments as $attachment) {
            if (is_string($attachment['file'] ?? null)) {
                $message->attach($attachment['file'], $attachment['options']['as'] ?? null);
            }
        }
        foreach ($mail->rawAttachments as $raw) {
            $message->attachData($raw['data'], $raw['name']);
        }
        return $message;
    }
}
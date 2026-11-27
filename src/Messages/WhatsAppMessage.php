<?php // Messages/WhatsAppMessage.php

namespace Nedieyassin\MessagingGateway\Messages;

use Nedieyassin\MessagingGateway\Enums\WhatsAppType;
use Nedieyassin\MessagingGateway\Messages\Concerns\HasContext;
use Nedieyassin\MessagingGateway\Support\File;

final class WhatsAppMessage
{
    use HasContext;

    public ?string $body = null;        // text
    public ?string $template = null;    // template name
    public string $language = 'en';
    public ?string $caption = null;     // document
    public ?File $file = null;          // document, or the document header of a template

    /** @var array<string, string> */
    public array $params = [];

    /** @var array<string, string> */
    public array $headerParams = [];

    /** @var array<int|string, string> keyed by the button's index in the template */
    public array $buttonParams = [];

    private function __construct(public readonly WhatsAppType $type)
    {
    }

    /** Free text. Only allowed within 24 hours of the contact's last message; otherwise use a template. */
    public static function text(string $body): WhatsAppMessage
    {
        $m = new WhatsAppMessage(WhatsAppType::TEXT);
        $m->body = $body;
        return $m;
    }

    public static function template(string $name, string $language = 'en'): WhatsAppMessage
    {
        $m = new WhatsAppMessage(WhatsAppType::TEMPLATE);
        $m->template = $name;
        $m->language = $language;

        return $m;
    }

    public static function document(File $file, ?string $caption = null): WhatsAppMessage
    {
        $m = new WhatsAppMessage(WhatsAppType::DOCUMENT);
        $m->file = $file;
        $m->caption = $caption;
        return $m;
    }

    /** Named body variables: ['guardian_name' => 'John']. For authentication templates: ['code' => '482913']. */
    public function params(array $params): WhatsAppMessage
    {
        $this->params = array_replace($this->params, $params);
        return $this;
    }

    public function headerParams(array $params): WhatsAppMessage
    {
        $this->headerParams = [...$this->headerParams, ...$params];
        return $this;
    }

    /** URL suffix, copy code or quick-reply payload, keyed by button index: [0 => 'INV-1042']. */
    public function buttonParams(array $params): WhatsAppMessage
    {
        foreach ($params as $index => $value) {
            $this->buttonParams[$index] = $value;
        }
        return $this;
    }

    /** The document header of a template. */
    public function attach(string $path, ?string $as = null): WhatsAppMessage
    {
        $this->file = File::fromPath($path, $as);
        return $this;
    }

    public function attachData(string $data, string $name): WhatsAppMessage
    {
        $this->file = File::fromData($data, $name);
        return $this;
    }

    public function attachFromStorage(string $disk, string $path, ?string $as = null): WhatsAppMessage
    {
        $this->file = File::fromStorage($disk, $path, $as);
        return $this;
    }
}
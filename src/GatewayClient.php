<?php

namespace Nedieyassin\MessagingGateway;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Nedieyassin\MessagingGateway\Enums\Channel;
use Nedieyassin\MessagingGateway\Enums\WhatsAppType;
use Nedieyassin\MessagingGateway\Exceptions\GatewayException;
use Nedieyassin\MessagingGateway\Messages\EmailMessage;
use Nedieyassin\MessagingGateway\Messages\PushMessage;
use Nedieyassin\MessagingGateway\Messages\SmsMessage;
use Nedieyassin\MessagingGateway\Messages\WhatsAppMessage;
use Nedieyassin\MessagingGateway\Support\Json;

/**
 * Thin client for the gateway's /v1 API. Every send returns as soon as the gateway has queued the message.
 * Use Gateway::message($id) to follow its status.
 */
final readonly class GatewayClient
{
    public function __construct(
        private readonly ?string $url = null,
        private readonly ?string $token = null,
        private readonly int     $timeout = 15,
        private readonly int     $retries = 3,
    )
    {
    }

    /** The same client, bound to other credentials. */
    public function for(GatewayCredentials $credentials): GatewayClient
    {
        return new GatewayClient($credentials->url ?? $this->url, $credentials->token, $this->timeout, $this->retries);
    }

    // ---------- sending ----------

    public function sendSms(string $to, SmsMessage $m, ?string $key = null): GatewayResponse
    {
        return $this->post(Channel::SMS, $this->http(), '/sms', $this->clean([
                'to' => $to,
                'text' => $m->text,
                'priority' => $m->priority ?: null,
                'scheduled_at' => $m->scheduledAt?->toIso8601String(),
                'recipient_name' => $m->recipientName,
            ] + $this->context($m, $key)));
    }

    /** @param list<string> $numbers the same text to every number, queued in one request */
    public function sendSmsBulk(array $numbers, SmsMessage $m, ?string $key = null): GatewayResponse
    {
        $items = array_map(fn(string $to) => $this->clean([
            'to' => $to,
            'text' => $m->text,
            'refs' => $m->refs ?: null,
        ]), array_values($numbers));

        return $this->post(Channel::SMS, $this->http(), '/sms/bulk', $this->clean([
            'messages' => $items,
            'priority' => $m->priority ?: null,
            'scheduled_at' => $m->scheduledAt?->toIso8601String(),
            'tenant_id' => $m->tenantId,
            'idempotency_key' => $m->idempotencyKey ?? $key,
        ]));
    }

    /**
     * @throws \JsonException
     */
    public function sendEmail(string $to, EmailMessage $m, ?string $key = null): GatewayResponse
    {
        $fields = $this->clean([
            'to' => $to,
            'subject' => $m->subject,
            'text' => $m->text,
            'html' => $m->html,
            'cc' => $m->cc ? implode(',', $m->cc) : null,
            'bcc' => $m->bcc ? implode(',', $m->bcc) : null,
            'reply_to' => $m->replyTo,
            'recipient_name' => $m->recipientName,
            'tenant_id' => $m->tenantId,
            'refs' => $m->refs ? Json::object($m->refs) : null,
            'idempotency_key' => $m->idempotencyKey ?? $key,
        ]);

        $request = $this->http()->asMultipart();
        foreach ($m->files as $file) {
            $request = $request->attach('files', $file->contents, $file->name);
        }

        return $this->post(Channel::EMAIL, $request, '/email', $fields);
    }

    /** @param list<string> $tokens up to 500; the gateway creates one message per token */
    public function sendPush(array $tokens, PushMessage $m, ?string $key = null): GatewayResponse
    {
        return $this->post(Channel::PUSH, $this->http(), '/push', $this->clean([
                'tokens' => array_values($tokens),
                'title' => $m->title,
                'body' => $m->body,
                'data' => $m->data ?: null,
                'image' => $m->image,
                'scheduled_at' => $m->scheduledAt?->toIso8601String(),
            ] + $this->context($m, $key)));
    }

    /**
     * @throws \JsonException
     */
    public function sendWhatsApp(string $to, WhatsAppMessage $m, ?string $key = null): GatewayResponse
    {
        $context = $this->context($m, $key);

        if ($m->type === WhatsAppType::TEXT) {
            return $this->post(Channel::WHATSAPP, $this->http(), '/whatsapp/text',
                $this->clean(['to' => $to, 'text' => $m->body] + $context));
        }

        $fields = $this->clean([
            'to' => $to,
            'tenant_id' => $context['tenant_id'] ?? null,
            'refs' => isset($context['refs']) ? Json::object($context['refs']) : null,
            'idempotency_key' => $context['idempotency_key'] ?? null,
        ]);
        $request = $this->http()->asMultipart();

        if ($m->type === WhatsAppType::DOCUMENT) {
            $fields['caption'] = $m->caption;
            $request = $request->attach('file', $m->file->contents, $m->file->name);

            return $this->post(Channel::WHATSAPP, $request, '/whatsapp/document', $this->clean($fields));
        }

        // template: these three must be JSON objects even when empty
        $fields += [
            'template' => $m->template,
            'language' => $m->language,
            'params' => Json::object($m->params),
            'header_params' => Json::object($m->headerParams),
            'button_params' => Json::object($m->buttonParams),
        ];
        if ($m->file) {
            $request = $request->attach('file', $m->file->contents, $m->file->name);
        }

        return $this->post(Channel::WHATSAPP, $request, '/whatsapp/template', $fields);
    }

    // ---------- reading ----------

    /** @return array<string, mixed> the message with its status, error_code, sent_at, delivered_at, ... */
    public function message(string $id): array
    {
        return $this->get("/messages/{$id}");
    }

    /** Filters: channel, status, number, tenant_id, since, until, limit, cursor, "ref.<key>", ... */
    public function messages(array $filters = []): array
    {
        return $this->get('/messages', $filters);
    }

    public function templates(): array
    {
        return $this->get('/templates');
    }

    public function smsBalance(): array
    {
        return $this->get('/sms/balance');
    }

    /** @param array{channel?: string, since?: string, until?: string, group_by?: string} $query */
    public function usage(array $query = []): array
    {
        return $this->get('/usage', $query);
    }

    // ---------- internals ----------

    private function http(): PendingRequest
    {
        if (! $this->url || ! $this->token) {
            throw GatewayException::noCredentials();
        }

        return Http::baseUrl(rtrim($this->url, '/').'/v1')
            ->withToken($this->token)
            ->acceptJson()
            ->timeout($this->timeout)
            ->connectTimeout(5);
    }

    private function context(object $m, ?string $key): array
    {
        return [
            'tenant_id' => $m->tenantId,
            'refs' => $m->refs ?: null,
            'idempotency_key' => $m->idempotencyKey ?? $key,
        ];
    }

    private function clean(array $data): array
    {
        return array_filter($data, fn($value) => $value !== null);
    }

    private function post(Channel $channel, PendingRequest $request, string $path, array $data): GatewayResponse
    {
        // Retrying a POST is only safe when the gateway can recognise the repeat
        if (isset($data['idempotency_key'])) {
            $request = $request->retry($this->retries, 250, fn($e) => $e instanceof ConnectionException, throw: false);
        }

        try {
            $response = $request->post($path, $data);
        } catch (ConnectionException $e) {
            throw GatewayException::connection($e);
        }
        if ($response->failed()) {
            throw GatewayException::fromResponse($response);
        }

        $json = $response->json();

        return new GatewayResponse($channel, $json['ids'] ?? array_filter([$json['id'] ?? null]));
    }

    private function get(string $path, array $query = []): array
    {
        try {
            $response = $this->http()->get($path, $query);
        } catch (ConnectionException $e) {
            throw GatewayException::connection($e);
        }
        if ($response->failed()) {
            throw GatewayException::fromResponse($response);
        }

        return $response->json();
    }
}
# Laravel Messaging Gateway

A Laravel package that sends notifications through the messaging gateway: WhatsApp, SMS, email and push (FCM). It adds four notification channels, a trait that finds a model's email, phone and FCM tokens, and a client for the gateway's API.

- **Sending is queued by the gateway.** A send returns as soon as the gateway accepts the message, so it is fast and works in queued or synchronous notifications.
- **Retries are safe.** Each notification, channel and recipient gets an idempotency key, so a retried job never sends twice.
- **One package for every system.** Each app uses its own gateway token, so messages, usage and billing stay separate per app.

## Contents

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Setting up a notifiable](#setting-up-a-notifiable)
4. [Writing notifications](#writing-notifications)
5. [Message builders](#message-builders)
6. [Using the client directly](#using-the-client-directly)
7. [Following message status](#following-message-status)
8. [Errors](#errors)
9. [Testing](#testing)
10. [Troubleshooting](#troubleshooting)

---

## Installation

Requires PHP 8.2+ and Laravel 11 or 12.

```bash
composer require nedieyassin/laravel-messaging-gateway
```

While the package lives in a private repository, add it to `composer.json` first:

```json
"repositories": [
  {
    "type": "vcs",
    "url": "git@github.com:nedieyassin/laravel-messaging-gateway.git"
  }
]
```

Add the gateway URL and the client token (created by the gateway admin) to `.env`:

```
MESSAGING_GATEWAY_URL=https://gateway.example.com
MESSAGING_GATEWAY_TOKEN=gw_xxxxxxxxxxxxxxxx
```

Publish the config file if you want to change the defaults:

```bash
php artisan vendor:publish --tag=gateway-config
```

The service provider is discovered automatically. It registers four channels:

| Channel  | Name to use in `via()` | Enum                |
|----------|------------------------|---------------------|
| WhatsApp | `gateway_whatsapp`     | `Channel::WHATSAPP` |
| SMS      | `gateway_sms`          | `Channel::SMS`      |
| Email    | `gateway_email`        | `Channel::EMAIL`    |
| Push     | `gateway_push`         | `Channel::PUSH`     |

A channel only works if the gateway admin has configured that channel's credentials for your client.

## Configuration

`config/gateway.php`:

| Key           | Default                   | Purpose                                                                                              |
|---------------|---------------------------|------------------------------------------------------------------------------------------------------|
| `url`         | `MESSAGING_GATEWAY_URL`   | Base URL of the gateway                                                                              |
| `token`       | `MESSAGING_GATEWAY_TOKEN` | Your client token                                                                                    |
| `timeout`     | `15`                      | HTTP timeout in seconds                                                                              |
| `retries`     | `3`                       | Attempts when the gateway can't be reached. Only requests that carry an idempotency key are retried. |
| `idempotency` | `true`                    | Generate one idempotency key per notification, channel and recipient                                 |
| `resolvers`   | see below                 | Where the trait finds emails, phones, tokens and the tenant                                          |

### Resolvers

`resolvers` lists where `ReceivesGatewayNotifications` looks, in order. Each entry is a path read with `data_get()`: an attribute (`phone`), a JSON or array attribute (`fcm_tokens`), or a relation with a wildcard (`devices.*.token`).

```php
'resolvers' => [
    'email'    => ['email'],
    'phone'    => ['phone', 'phone_number', 'mobile'],
    'whatsapp' => [],                                       // empty = use the phone
    'push'     => ['fcm_token', 'fcm_tokens', 'devices.*.token'],
    'tenant'   => null,                                     // e.g. 'school_id'
],
```

- Email, phone and WhatsApp use the **first valid** value. Phones must have 8 to 15 digits, and emails must pass `FILTER_VALIDATE_EMAIL`.
- Push uses **every** token found, with duplicates removed.
- A model can override any of this by defining the method itself (next section).

## Setting up a notifiable

Add the trait to any model you notify, next to `Notifiable`:

```php
use Illuminate\Notifications\Notifiable;
use Nedieyassin\MessagingGateway\Concerns\ReceivesGatewayNotifications;

class User extends Authenticatable
{
    use Notifiable, ReceivesGatewayNotifications;
}
```

If your columns match the defaults, you're done. Otherwise, override the resolver you need:

| Method                    | Returns    | Used by                                                                      |
|---------------------------|------------|------------------------------------------------------------------------------|
| `gatewayEmail()`          | `?string`  | Email                                                                        |
| `gatewaySmsNumber()`      | `?string`  | SMS (and WhatsApp when there's no separate number)                           |
| `gatewayWhatsAppNumber()` | `?string`  | WhatsApp. Falls back to `gatewayPhone()`.                                    |
| `gatewayFcmTokens()`      | `string[]` | Push                                                                         |
| `gatewayTenantId()`       | `?string`  | Tags every message with a tenant (see [Tenants and refs](#tenants-and-refs)) |

```php
class User extends Authenticatable
{
    use Notifiable, ReceivesGatewayNotifications;

    public function gatewaySmsNumber(): ?string
    {
        return $this->mobile_number;
    }
    
    public function gatewayWhatsAppNumber(): ?string
    {
        return $this->mobile_number;
    }

    public function gatewayFcmTokens(): array
    {
        return $this->devices()->whereNotNull('fcm_token')->pluck('fcm_token')->all();
    }

    public function gatewayTenantId(): ?string
    {
        return (string) $this->school_id;
    }
}
```

**Phone numbers** can be stored as `0999123456`, `+265999123456` or `265999123456`: the gateway normalises them. A number without a country code and without a leading `0` is sent as it is, so avoid storing those.

## Writing notifications

A notification chooses its channels in `via()` and builds a message for each one in a `toGateway…` method.

| Channel  | Method on the notification                                 | Returns                             |
|----------|------------------------------------------------------------|-------------------------------------|
| SMS      | `toGatewaySms($notifiable)`                                | `SmsMessage` (or a plain string)    |
| WhatsApp | `toGatewayWhatsApp($notifiable)`                           | `WhatsAppMessage`                   |
| Email    | `toGatewayEmail($notifiable)`, or your existing `toMail()` | `EmailMessage` (or a `MailMessage`) |
| Push     | `toGatewayPush($notifiable)`                               | `PushMessage`                       |

### One or more fixed channels

```php
use Illuminate\Notifications\Notification;
use Nedieyassin\MessagingGateway\Enums\Channel;
use Nedieyassin\MessagingGateway\Messages\SmsMessage;

class OtpNotification extends Notification
{
    public function __construct(private string $code) {}

    public function via(object $notifiable): array
    {
        return [Channel::SMS->driver()];   // 'gateway_sms'
    }

    public function toGatewaySms(object $notifiable): SmsMessage
    {
        return SmsMessage::make("Your code is {$this->code}")->priority();
    }
}
```

A channel the notifiable has no address for is **skipped silently**. Nothing is sent and nothing fails.

### Choosing channels by what the person has

`UsesGatewayChannels` gives you `gatewayVia()`, which returns only the channels the notifiable can be reached on:

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Nedieyassin\MessagingGateway\Concerns\UsesGatewayChannels;
use Nedieyassin\MessagingGateway\Enums\Channel;

class FeeReminder extends Notification implements ShouldQueue
{
    use Queueable, UsesGatewayChannels;

    public function via(object $notifiable): array
    {
        // push, else WhatsApp, else SMS
        return $this->gatewayVia($notifiable, [Channel::PUSH, Channel::WHATSAPP, Channel::SMS], firstOnly: true);
    }
    // toGatewayPush(), toGatewayWhatsApp(), toGatewaySms() ...
}
```

- With `firstOnly: true` the first channel that has an address wins. Leave it out to send on **every** available channel.
- This is a preference by *having an address*, not a delivery fallback. If the push fails, WhatsApp is not tried. The gateway doesn't fall back either.

### Email

An existing notification with `toMail()` works unchanged: the channel renders the `MailMessage` and sends it through the gateway. Attachments given as file paths or raw data are carried over. For more control, define `toGatewayEmail()`:

```php
public function via($n): array { return [Channel::EMAIL->driver()]; }

public function toGatewayEmail($n): EmailMessage
{
    return EmailMessage::make('Term 2 report')
        ->view('mail.report', ['student' => $this->student])
        ->attach($this->pdfPath)
        ->cc('office@school.mw');
}
```

### Anonymous recipients

```php
use Illuminate\Support\Facades\Notification;

Notification::route(Channel::SMS->driver(), '0999123456')->notify(new OtpNotification($code));

Notification::route(Channel::EMAIL->driver(), 'parent@example.com')
    ->route(Channel::SMS->driver(), '0999123456')
    ->notify(new TermReport($report));
```

### What the channels do for you

- **Several addresses:** One WhatsApp message or email per address. SMS goes out as one bulk request when there are several numbers (the same text to each). Push is sent in chunks of 500 tokens.
- **Tenant:** If the message doesn't set a tenant, the notifiable's `gatewayTenantId()` is used.
- **Idempotency:** One key per notification, channel and recipient (see [Errors](#errors)).

## Message builders

Every builder shares these methods:

| Method                              | Purpose                                                                                                                                           |
|-------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------|
| `tenant($id)`                       | Tag the message with a tenant (for example a school) for filtering and per-tenant reports. Up to 64 characters.                                   |
| `refs([...])` / `ref($key, $value)` | Up to 10 tags such as `student` or `invoice`. Keys use `a-z`, digits and `_`; values up to 128 characters. You can filter messages by them later. |
| `idempotencyKey($key)`              | Replace the generated key                                                                                                                         |
| `recipientName($name)`              | Stored with SMS and email messages                                                                                                                |

### SmsMessage

```php
use Nedieyassin\MessagingGateway\Messages\SmsMessage;

SmsMessage::make('Fees of MK 50,000 are due Friday.')
    ->priority()                       // skip the batch window: use for OTPs
    ->later(now()->addHour())          // or schedule it (up to a year ahead)
    ->ref('invoice', 9876);
```

Text can be up to 1600 characters (160 per SMS page). Without `priority()`, the gateway groups queued SMS into batches that go out at least every 20 seconds, so an ordinary SMS can take up to about 20 seconds to leave.

### WhatsAppMessage

WhatsApp allows **free text only within 24 hours of the contact's last message to you**. Outside that window, use a template.

```php
use Nedieyassin\MessagingGateway\Messages\WhatsAppMessage;
use Nedieyassin\MessagingGateway\Support\File;

// Free text
WhatsAppMessage::text('Your report is ready.');

// Template with named variables and a URL button
WhatsAppMessage::template('fee_reminder', 'en')
    ->params(['guardian_name' => 'John', 'balance' => 'MK 50,000'])
    ->headerParams(['term' => 'Term 2'])       // only for templates with a text variable in the header
    ->buttonParams([0 => 'INV-1042'])          // keyed by the button's index in the template
    ->attach(storage_path('app/invoice.pdf')); // only for templates with a document header

// Document
WhatsAppMessage::document(File::fromPath($path, 'statement.pdf'), 'Fees statement');
```

Button parameters, keyed by the button's index:

| Button                                                | Value                                                              |
|-------------------------------------------------------|--------------------------------------------------------------------|
| URL with a variable (`https://pay.example.com/{{1}}`) | The URL suffix (required)                                          |
| Copy code                                             | The code, up to 15 characters (required)                           |
| Quick reply                                           | Optional payload returned when the user taps, up to 128 characters |
| Phone number, fixed URL                               | Nothing                                                            |

`Gateway::templates()` lists every template with its variables and its buttons (including each button's `index` and whether a value is `required`).

**Authentication (OTP) templates** take a single param: `->params(['code' => '482913'])`. The code is used in both the body and the button, so don't send `buttonParams`.

Templates must be approved by Meta and use named variables. Templates with image, video or location headers, numbered `{{1}}` variables, or Flow or catalog buttons aren't supported.

### EmailMessage

```php
use Nedieyassin\MessagingGateway\Messages\EmailMessage;

EmailMessage::make('Term 2 report')
    ->text('Please find the report attached.')
    ->html('<p>Please find the report attached.</p>')   // or ->view('mail.report', $data)
    ->cc(['head@school.mw'])->bcc('archive@school.mw')
    ->replyTo('office@school.mw')
    ->attach($path, 'report.pdf')                        // or attachData($bytes, $name), attachFromStorage($disk, $path)
    ->tenant(12);
```

Send text, HTML or both. Attachments can be pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv, png, jpg and jpeg, up to the gateway's upload limit (20 MB by default). Email is only tracked up to `SENT` (accepted by the SMTP server). Bounces are not reported.

### PushMessage

```php
use Nedieyassin\MessagingGateway\Messages\PushMessage;

PushMessage::make('Report ready', "Chikondi's Term 2 report is available")
    ->data(['screen' => 'reports', 'student' => 345])   // custom payload for your app
    ->image('https://example.com/banner.png');
```

The gateway creates one message per device token, so each token has its own status. A token FCM no longer accepts fails with `error_code: UNREGISTERED`. Remove those tokens on your side.

### Tenants and refs

`tenant` and `refs` are stored with each message. Use them to find messages and to see usage by school, invoice or student:

```php
Gateway::messages(['tenant_id' => '12', 'ref.invoice' => 9876]);
```

## Using the client directly

For code that isn't a notification (jobs, console commands, controllers), use the facade:

```php
use Nedieyassin\MessagingGateway\Facades\Gateway;

$r = Gateway::sendSms('0999123456', SmsMessage::make('Hello'));
$r = Gateway::sendSmsBulk(['0999123456', '0888123456'], SmsMessage::make('Fees due Friday'), key: 'fees-2026-11-30');
$r = Gateway::sendEmail('parent@example.com', $emailMessage);
$r = Gateway::sendPush([$token1, $token2], $pushMessage);
$r = Gateway::sendWhatsApp('0999123456', $whatsAppMessage);

$r->ids;   // gateway message ids, one per recipient or device token
$r->id();  // the first one
```

Every send method takes an optional `$key` (the idempotency key). Pass your own when you send outside a notification and want repeats to be safe.

Read methods:

| Method                        | Returns                                                                                                                               |
|-------------------------------|---------------------------------------------------------------------------------------------------------------------------------------|
| `Gateway::message($id)`       | One message with its status, error and timestamps                                                                                     |
| `Gateway::messages($filters)` | A page of messages. Filters: `channel`, `status`, `number`, `tenant_id`, `since`, `until`, `limit`, `cursor`, `ref.<key>`, and so on. |
| `Gateway::templates()`        | Synced WhatsApp templates with variables and buttons                                                                                  |
| `Gateway::smsBalance()`       | Balance of your SMS application                                                                                                       |
| `Gateway::usage($query)`      | Usage totals: `group_by` (`channel`, `day`, `category`), `since`, `until`                                                             |

## Following message status

A send only confirms that the gateway **queued** the message. To follow it, keep the ids and look them up:

```php
$status = Gateway::message($id)['status'];   // QUEUED, SENDING, SENT, DELIVERED, READ, FAILED
```

`Nedieyassin\MessagingGateway\Enums\MessageStatus` wraps these values (`->failed()`, `->pending()`).

| Channel     | Statuses you can expect                  |
|-------------|------------------------------------------|
| WhatsApp    | `SENT`, `DELIVERED`, `READ`, or `FAILED` |
| SMS         | `SENT`, then `DELIVERED` or `FAILED`     |
| Email, push | `SENT` or `FAILED`                       |

A failed message has `error_code` and `error_message`. Temporary failures are retried by the gateway automatically.

Laravel passes each channel's return value to `NotificationSent` listeners, so you can store the ids when they are sent:

```php
use Illuminate\Notifications\Events\NotificationSent;
use Nedieyassin\MessagingGateway\GatewayResponse;

Event::listen(NotificationSent::class, function (NotificationSent $event) {
    if ($event->response instanceof GatewayResponse) {
        // $event->response->channel, ->ids
    }
});
```

## Multi-tenant credentials

In a system where each tenant (school, company, branch) has its own gateway client, the token can't live in `.env` alone. The package resolves the token **at the moment a message is sent**, so it is also correct in queued notifications: the job runs in your worker, your tenancy package has restored the tenant, and the token is looked up then. It is never stored in the queued job.

### Ways to supply credentials

Pick the one that fits. They are checked in this order, and the first one that gives a token wins.

| # | Where                                                                                       | Best for                                                                                |
|---|---------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------|
| 1 | `gatewayCredentials($notifiable)` on the **notification**                                   | One notification that always uses a specific client                                     |
| 2 | `gatewayCredentials()` on the **notifiable** (the trait reads `resolvers.token` by default) | The token belongs to the recipient's tenant, such as `$guardian->school->gateway_token` |
| 3 | `Gateway::withCredentials($token, fn () => ...)`                                            | A scope around a block of code                                                          |
| 4 | `Gateway::resolveCredentialsUsing(fn ...)` (global)                                         | The **current tenant**: the usual answer for tenancy packages                           |
| 5 | `GATEWAY_TOKEN` in `.env`                                                                   | A default (for example, your own system's client)                                       |

**1. The current tenant (global resolver).** Register it once in a service provider's `boot()`:

```php
use Illuminate\Notifications\Notification;
use MessagingGateway\Facades\Gateway;

Gateway::resolveCredentialsUsing(function (?object $notifiable, ?Notification $notification) {
    return tenant()?->gateway_token;          // stancl/tenancy
    // return Tenant::current()?->gateway_token;   // spatie/laravel-multitenancy
});
```

The callback returns a token string, `['token' => ..., 'url' => ...]`, a `GatewayCredentials`, or `null`. It is also called for direct `Gateway::` calls, with both arguments `null`.

**2. The notifiable.** Point the trait at the right place with config, or override the method:

```php
// config/gateway.php
'resolvers' => ['token' => 'school.gateway_token'],   // read with data_get() on the model

// or on the model
public function gatewayCredentials(): GatewayCredentials|string|null
{
    return $this->school->gateway_token;
}
```

**3. The notification.** With the `UsesGatewayChannels` trait you can either hand it the token when you send:

```php
$guardian->notify((new FeeReminder($invoice))->usingGatewayCredentials($school->gateway_token));
```

or have it look the token up when it is sent:

```php
protected function resolveGatewayCredentials(object $notifiable): ?string
{
    return $this->invoice->school->gateway_token;
}
```

`usingGatewayCredentials()` accepts a token string, `['token' => ..., 'url' => ...]`, or a `GatewayCredentials`. The token is stored **encrypted** on the notification, so it is safe in a queued job. It requires the web app and the queue workers to share the same `APP_KEY`, and it is fixed at the moment you call it. Use `resolveGatewayCredentials()` when it must be read at send time (for example if tenants rotate tokens). A notification
that doesn't use the trait can define `gatewayCredentials(object $notifiable)` itself.

**4. Explicit or scoped.**

```php
// a client bound to a token
Gateway::using($school->gateway_token)->sendSms('0999123456', SmsMessage::make('Hello'));
Gateway::using($token, 'https://gateway-2.example.com')->message($id);   // different gateway URL

// everything inside the callback, notifications included
Gateway::withCredentials($school->gateway_token, function () use ($guardian) {
    $guardian->notify(new FeeReminder($invoice));
});
```

**Anonymous notifiables** (`Notification::route(...)`) have no model to read a token from. Use the notification's `gatewayCredentials()`, a scope, or the global resolver.

### Make a missing token an error

If nothing resolves a token, the package uses `GATEWAY_TOKEN`. In a multi-tenant system that would send as the wrong client (and bill it). Turn the fallback off:

```
GATEWAY_USE_DEFAULT=false
```

Then a send with no resolved credentials throws `GatewayException` with code `NO_CREDENTIALS` (not retryable) before anything is sent.

### Rules for queued notifications

- **Don't put the token in the notification's constructor or properties.** Queued notifications are serialized into the queue. Return the token from `gatewayCredentials()` or the resolver, so it is read when the job runs. `GatewayCredentials` objects refuse to be serialized to catch this, but a plain string would not be caught.
- **The resolver must read the tenant at call time.** `tenant()` and `Tenant::current()` already do, as long as your tenancy package restores the tenant for queued jobs. Stancl's `QueueTenancyBootstrapper` and Spatie's tenant-aware jobs both do. Check that yours does.
- **Store tokens encrypted** in your tenant tables (`'gateway_token' => 'encrypted'` in the model's `casts`).
- With **Octane on Swoole**, `withCredentials()` is not safe across concurrent coroutines. Use `Gateway::using()` or the resolver there.

## Errors

Failed requests throw `MessagingGateway\Exceptions\GatewayException`:

| Property or method | Meaning                                                                                                                     |
|--------------------|-----------------------------------------------------------------------------------------------------------------------------|
| `status`           | HTTP status, or `null` when the gateway couldn't be reached                                                                 |
| `errorCode`        | The gateway's code (`OUTSIDE_WINDOW`, `CHANNEL_NOT_CONFIGURED`, `INVALID_NUMBER`, ...), `VALIDATION_ERROR`, or `CONNECTION` |
| `getMessage()`     | A readable message                                                                                                          |
| `detail`           | The raw error details                                                                                                       |
| `retryable()`      | `true` for connection errors, 429 and 5xx. Anything else is a problem with the request.                                     |
| `outsideWindow()`  | `true` for WhatsApp free text outside the 24-hour window                                                                    |

In a queued notification, an exception makes Laravel retry or fail the job as usual. To react to a failure, use the notification's `failed()` method:

```php
public function failed(\Throwable $e): void
{
    if ($e instanceof GatewayException && $e->outsideWindow()) {
        // send a template instead
    }
}
```

Common codes:

| Code                                                            | Cause                                                                                      |
|-----------------------------------------------------------------|--------------------------------------------------------------------------------------------|
| `CHANNEL_NOT_CONFIGURED`                                        | The gateway admin hasn't set up that channel for your client                               |
| `OUTSIDE_WINDOW`                                                | WhatsApp free text, but the contact hasn't written to you in 24 hours                      |
| `INVALID_NUMBER`, `INVALID_EMAIL`                               | Bad recipient                                                                              |
| `TEMPLATE_NOT_FOUND`, `TEMPLATE_NOT_APPROVED`, `INVALID_PARAMS` | Template problems. `INVALID_PARAMS` lists the missing or unexpected variables and buttons. |
| `CLIENT_REQUIRED`                                               | The gateway admin secret was used as the token                                             |
| `VALIDATION_ERROR`                                              | A field is invalid. The message names the field.                                           |

**Retries and duplicates.** The channels send one idempotency key per notification, channel and recipient. If a job is retried, or a request is repeated after a timeout, the gateway returns the original message instead of sending again. A notification that reaches several recipients and fails halfway can be retried safely too. Set `'idempotency' => false` to turn this off.

## Testing

Fake the HTTP calls to the gateway in your own tests:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    '*/v1/sms'      => Http::response(['id' => '01k0sms', 'status' => 'QUEUED'], 202),
    '*/v1/sms/bulk' => Http::response(['count' => 2, 'ids' => ['01k0a', '01k0b']], 202),
    '*/v1/push'     => Http::response(['count' => 1, 'ids' => ['01k0push']], 202),
]);

$guardian->notify(new FeeReminder($invoice));

Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/sms')
    && $request['to'] === '0999123456'
    && $request['tenant_id'] === '12');
```

To test only that your notification chooses the right channels, use `Notification::fake()` and `Notification::assertSentTo($guardian, FeeReminder::class, fn ($n, $channels) => $channels === ['gateway_sms'])`.

## Troubleshooting

| Symptom                                          | Check                                                                                                                                                         |
|--------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Set GATEWAY_URL and GATEWAY_TOKEN.`             | Both variables are set, and the config isn't cached with old values (`php artisan config:clear`)                                                              |
| Nothing is sent and nothing fails                | The notifiable has no address for that channel. Check what the trait resolves: `$user->gatewayPhone()`, `$user->gatewayEmail()`, `$user->gatewayFcmTokens()`. |
| Resolver returns the wrong value                 | The `resolvers` config, or override the method on the model                                                                                                   |
| `LogicException: ... must define toGatewaySms()` | The notification lists the channel in `via()` but has no matching method (see the table in [Writing notifications](#writing-notifications))                   |
| `CHANNEL_NOT_CONFIGURED`                         | Ask the gateway admin to add the credentials for that channel to your client                                                                                  |
| `401`                                            | Wrong or revoked token, or your client is suspended                                                                                                           |
| WhatsApp text fails with `OUTSIDE_WINDOW`        | Use a template, or wait for the contact to message you first                                                                                                  |
| Template fails with `INVALID_PARAMS`             | The message includes which variables or button indexes are missing or unexpected. `Gateway::templates()` shows what the template expects.                     |
| An SMS takes about 20 seconds                    | The gateway batches SMS. Use `->priority()` for time-critical messages.                                                                                       |
| Message stays `SENT`, never `DELIVERED`          | Email and push never go past `SENT`. For SMS and WhatsApp, delivery reports arrive from the provider and can take a few minutes.                              |
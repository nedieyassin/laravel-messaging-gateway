<?php

return [
    'url' => env('MESSAGING_GATEWAY_URL'),     // e.g. https://gateway.example.com
    'token' => env('MESSAGING_GATEWAY_TOKEN'), // the client token (gw_...)
    'timeout' => env('MESSAGING_GATEWAY_TIMEOUT', 15),
    'use_default_credentials' => env('MESSAGING_GATEWAY_USE_DEFAULT', true),

    // Attempts for a request that fails to connect. Only requests carrying an idempotency key are retried.
    'retries' => 3,

    // One key per notification + channel + recipient, so queue retries never send twice.
    'idempotency' => true,

    /*
     * Where the ReceivesGatewayNotifications trait looks, in order. Each entry is a path read with
     * data_get() on the model: an attribute ('phone'), a JSON/array attribute ('fcm_tokens'), or a relation
     * with a wildcard ('devices.*.token'). The first valid email / phone wins; every token is used.
     * A model can also override gatewayEmail(), gatewaySmsNumber(), gatewayWhatsAppNumber(),
     * gatewayFcmTokens() and gatewayTenantId().
     */
    'resolvers' => [
        'token' => null,                 // path on the notifiable, e.g. 'school.gateway_token'
        'url' => null,                   // only if tenants use different gateways
        'email' => ['email'],
        'phone' => ['phone', 'phone_number', 'mobile'],
        'whatsapp' => [],                // empty = use the phone
        'push' => ['fcm_token', 'fcm_tokens', 'devices.*.token'],
        'tenant' => null,                // attribute holding the gateway tenant_id, e.g. 'school_id'
    ],
];
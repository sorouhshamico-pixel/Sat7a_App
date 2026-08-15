<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sms' => [
        'driver' => env('SMS_PROVIDER_DRIVER', 'log'),
        'api_key' => env('SMS_PROVIDER_API_KEY'),
        'sender_id' => env('SMS_PROVIDER_SENDER_ID'),
    ],

    // No real vendor exists for any of these yet — every driver defaults
    // to 'log' (see App\Providers\NotificationServiceProvider and
    // docs/NOTIFICATIONS.md), same pattern as `sms` above.
    'push' => [
        'driver' => env('PUSH_PROVIDER_DRIVER', 'log'),
        'api_key' => env('PUSH_PROVIDER_API_KEY'),
    ],

    'email' => [
        'driver' => env('EMAIL_PROVIDER_DRIVER', 'log'),
        'api_key' => env('EMAIL_PROVIDER_API_KEY'),
        'from_address' => env('EMAIL_PROVIDER_FROM_ADDRESS'),
    ],

    'whatsapp' => [
        'driver' => env('WHATSAPP_PROVIDER_DRIVER', 'log'),
        'api_key' => env('WHATSAPP_PROVIDER_API_KEY'),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'payments' => [
        'driver' => env('PAYMENT_GATEWAY_DRIVER', 'fake'),
        'public_key' => env('PAYMENT_GATEWAY_PUBLIC_KEY'),
        'secret_key' => env('PAYMENT_GATEWAY_SECRET_KEY'),
        'webhook_secret' => env('PAYMENT_GATEWAY_WEBHOOK_SECRET'),
        // No real gateway is configured, so there's no real processing
        // cost to model — 0 until a real gateway's actual fee schedule
        // is known (see docs/SETTLEMENT_ARCHITECTURE.md and
        // App\Domain\Ledger\Actions\RecordPaymentLedgerEntriesAction).
        'gateway_fee_percentage' => (float) env('PAYMENT_GATEWAY_FEE_PERCENTAGE', 0),
        'fake' => [
            // A real gateway secret is never set for a driver nobody has
            // credentials for — this fallback lets the fake adapter's
            // webhook signature verification (see
            // App\Domain\Payments\Adapters\Fake\FakePaymentGateway) still
            // run for real in dev/tests instead of trivially always
            // passing.
            'webhook_secret' => env('PAYMENT_GATEWAY_WEBHOOK_SECRET') ?: 'fake-gateway-local-dev-webhook-secret',
        ],
    ],

];

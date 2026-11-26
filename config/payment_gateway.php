<?php

return [
    'enabled' => env('PAYMENT_GATEWAY_ENABLED', false),
    'environment' => env('PAYMENT_GATEWAY_ENV', 'sandbox'),
    'provider' => env('PAYMENT_GATEWAY_PROVIDER', 'paymongo'),
    // Operator must confirm this instance contains test contributions only.
    'sandbox_data_confirmed' => env('PAYMENT_GATEWAY_SANDBOX_DATA_CONFIRMED', false),
    'secret_key' => env('PAYMONGO_SECRET_KEY'),
    'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
        'business_id' => env('XENDIT_BUSINESS_ID'),
        'treasurer_account_confirmed' => env('XENDIT_TREASURER_ACCOUNT_CONFIRMED', false),
        'test_database' => env('XENDIT_TEST_DATABASE'),
    ],
];

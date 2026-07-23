<?php

/*
|--------------------------------------------------------------------------
| DOKU Checkout configuration
|--------------------------------------------------------------------------
|
| Credentials come from the environment only — never hard-code them, and never
| store them in the database. Business services read the resolved flat keys
| (`client_id`, `secret_key`, `base_url`, `environment`) and never `env()`
| directly, so config caching keeps working.
|
| BOTH environments are configured side by side. Which one is live is a runtime
| decision held in `system_settings.doku_environment` and applied on boot by
| DokuServiceProvider, so a super admin can switch sandbox <-> production from
| the back office without a deploy. The credentials themselves never move.
|
| Reference: https://developers.doku.com/accept-payments/doku-checkout
|
*/

$default = env('DOKU_ENVIRONMENT', 'sandbox');

return [
    /*
    | Fallback when system_settings holds nothing yet (fresh install, or the
    | settings table is unavailable). Also the value the switcher resets to.
    */
    'default_environment' => $default,

    /*
    | Per-environment credentials. The legacy flat DOKU_CLIENT_ID /
    | DOKU_SECRET_KEY / DOKU_BASE_URL variables still work: they apply to
    | whichever environment DOKU_ENVIRONMENT names, so an existing .env keeps
    | running unchanged.
    */
    'environments' => [
        'sandbox' => [
            'label' => 'Sandbox (uji coba, tanpa uang sungguhan)',
            'base_url' => rtrim((string) env(
                'DOKU_SANDBOX_BASE_URL',
                $default === 'sandbox' ? env('DOKU_BASE_URL', 'https://api-sandbox.doku.com') : 'https://api-sandbox.doku.com'
            ), '/'),
            'client_id' => env('DOKU_SANDBOX_CLIENT_ID', $default === 'sandbox' ? env('DOKU_CLIENT_ID') : null),
            'secret_key' => env('DOKU_SANDBOX_SECRET_KEY', $default === 'sandbox' ? env('DOKU_SECRET_KEY') : null),
            'dashboard_url' => 'https://sandbox.doku.com/bo/developer/api-keys',
        ],

        'production' => [
            'label' => 'Produksi (transaksi nyata, uang sungguhan)',
            'base_url' => rtrim((string) env(
                'DOKU_PRODUCTION_BASE_URL',
                $default === 'production' ? env('DOKU_BASE_URL', 'https://api.doku.com') : 'https://api.doku.com'
            ), '/'),
            'client_id' => env('DOKU_PRODUCTION_CLIENT_ID', $default === 'production' ? env('DOKU_CLIENT_ID') : null),
            'secret_key' => env('DOKU_PRODUCTION_SECRET_KEY', $default === 'production' ? env('DOKU_SECRET_KEY') : null),
            'dashboard_url' => 'https://dashboard.doku.com/bo/developer/api-keys',
        ],
    ],

    /*
    | Resolved active credentials. DokuServiceProvider overwrites these on boot
    | from the stored environment; the values here are only the boot-time
    | defaults so console commands and tests still work before that runs.
    */
    'environment' => $default,
    'client_id' => env('DOKU_CLIENT_ID'),
    'secret_key' => env('DOKU_SECRET_KEY'),
    'base_url' => rtrim((string) env('DOKU_BASE_URL', 'https://api-sandbox.doku.com'), '/'),

    // Path only — it is also the Request-Target used in the signature.
    'checkout_path' => '/checkout/v1/payment',

    'notification_url' => env('DOKU_NOTIFICATION_URL'),
    'callback_url' => env('DOKU_CALLBACK_URL'),

    // Minutes the DOKU payment page stays valid. Kept <= the booking hold so a
    // payment page can never outlive the inventory reservation.
    'payment_due_minutes' => (int) env('DOKU_PAYMENT_DUE_MINUTES', 30),

    'currency' => env('HOTEL_CURRENCY', 'IDR'),

    'timeout' => (int) env('DOKU_HTTP_TIMEOUT', 30),

    /*
    | Which payment methods the DOKU checkout page offers. Comma-separated in
    | .env (DOKU_PAYMENT_METHODS). Empty => DOKU shows every method enabled on
    | the account. A single method sends the guest straight to it — this is how
    | "QRIS only" works. Values must match DOKU's documented identifiers below.
    */
    'payment_method_types' => array_filter(
        array_map('trim', explode(',', (string) env('DOKU_PAYMENT_METHODS', 'QRIS')))
    ),

    // DOKU Checkout supported payment method identifiers (validation allowlist).
    'known_payment_method_types' => [
        'VIRTUAL_ACCOUNT_BCA',
        'VIRTUAL_ACCOUNT_BANK_MANDIRI',
        'VIRTUAL_ACCOUNT_BANK_SYARIAH_MANDIRI',
        'VIRTUAL_ACCOUNT_DOKU',
        'VIRTUAL_ACCOUNT_BRI',
        'VIRTUAL_ACCOUNT_BNI',
        'VIRTUAL_ACCOUNT_BANK_PERMATA',
        'VIRTUAL_ACCOUNT_BANK_CIMB',
        'VIRTUAL_ACCOUNT_BANK_DANAMON',
        'VIRTUAL_ACCOUNT_BNC',
        'VIRTUAL_ACCOUNT_BTN',
        'ONLINE_TO_OFFLINE_ALFA',
        'CREDIT_CARD',
        'DIRECT_DEBIT_BRI',
        'EMONEY_SHOPEEPAY',
        'EMONEY_OVO',
        'EMONEY_DANA',
        'QRIS',
        'PEER_TO_PEER_AKULAKU',
        'PEER_TO_PEER_KREDIVO',
        'PEER_TO_PEER_INDODANA',
    ],

    /*
    | Maps DOKU transaction.status -> internal handling.
    | Anything NOT listed here is treated as "review" — an unknown status must
    | never silently confirm a booking.
    */
    'status_map' => [
        'SUCCESS' => 'paid',
        'PENDING' => 'pending',
        'FAILED' => 'failed',
        'EXPIRED' => 'expired',
        'REFUNDED' => 'refunded',
        'VOID' => 'failed',
    ],
];

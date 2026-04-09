<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Midtrans Configuration
    |--------------------------------------------------------------------------
    | Daftar di https://dashboard.midtrans.com untuk mendapatkan key.
    | Gunakan key Sandbox untuk testing, Production untuk live.
    */

    'server_key'     => env('MIDTRANS_SERVER_KEY'),
    'client_key'     => env('MIDTRANS_CLIENT_KEY'),
    'is_production'  => env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized'   => env('MIDTRANS_IS_SANITIZED', true),
    'is_3ds'         => env('MIDTRANS_IS_3DS', true),

    // URL callback dari Midtrans setelah pembayaran
    'notification_url' => env('MIDTRANS_NOTIFICATION_URL', env('APP_URL') . '/api/v1/payment/notification'),

    // Redirect setelah pembayaran (untuk web)
    'finish_url'   => env('MIDTRANS_FINISH_URL',   env('APP_URL') . '/payment/finish'),
    'unfinish_url' => env('MIDTRANS_UNFINISH_URL', env('APP_URL') . '/payment/unfinish'),
    'error_url'    => env('MIDTRANS_ERROR_URL',    env('APP_URL') . '/payment/error'),
];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services Configuration
    |--------------------------------------------------------------------------
    | Konfigurasi untuk layanan pihak ketiga
    |
    | Location Service menggunakan Nominatim API (OpenStreetMap) - GRATIS!
    | Tidak perlu API key apapun.
    */

    // Location API Configuration
    // Uses Nominatim (OpenStreetMap) - FREE and NO API KEY REQUIRED!
    'location' => [
        'provider' => 'nominatim',  // nominatim = OpenStreetMap Nominatim API
        'base_url' => 'https://nominatim.openstreetmap.org',
    ],

    // Google OAuth Configuration
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

    // Facebook OAuth Configuration
    'facebook' => [
        'client_id'     => env('FACEBOOK_APP_ID'),
        'client_secret' => env('FACEBOOK_APP_SECRET'),
        'redirect'      => env('FACEBOOK_REDIRECT_URI'),
    ],

    // Frontend URL for OAuth redirects
    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:3000'),
        'callback_path' => env('FRONTEND_AUTH_CALLBACK_PATH', '/auth/callback'),
    ],
];


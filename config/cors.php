<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS — Cross-Origin Resource Sharing
    |--------------------------------------------------------------------------
    | Konfigurasi ini mengizinkan aplikasi mobile (React Native / Flutter)
    | mengakses API dari origin yang berbeda.
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],   // Ganti dengan domain spesifik di production

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];

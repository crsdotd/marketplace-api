<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name'    => 'Marketplace API',
        'version' => '1.0.0',
        'status'  => 'running',
        'docs'    => url('/api/v1'),
    ]);
});

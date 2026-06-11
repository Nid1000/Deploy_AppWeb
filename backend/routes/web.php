<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'Delicias API',
    'status' => 'ok',
    'health' => url('/api/health'),
]));

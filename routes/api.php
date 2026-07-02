<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['ok' => true]));

use App\Http\Controllers\Api\TicketController;

Route::post('/tickets', [TicketController::class, 'store'])->name('api.tickets.store');

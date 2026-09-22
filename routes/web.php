<?php

use Illuminate\Support\Facades\Route;

/*
| Liveness probe for load balancers / uptime monitoring.
| (Laravel's built-in /up endpoint is also available.)
*/
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'otueke-booking-web',
]))->name('health');

/*
| Phase 0 placeholder home page. Facility data, availability and bookings
| will be loaded from the Otueke API booking engine in a later phase.
*/
Route::view('/', 'home')->name('home');

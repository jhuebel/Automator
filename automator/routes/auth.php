<?php

use App\Http\Controllers\Auth\SsoController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('login', 'pages.auth.login')
        ->name('login');

    Route::get('auth/{provider}/redirect', [SsoController::class, 'redirect'])
        ->whereIn('provider', ['entra', 'google'])
        ->name('sso.redirect');

    Route::get('auth/{provider}/callback', [SsoController::class, 'callback'])
        ->whereIn('provider', ['entra', 'google'])
        ->name('sso.callback');
});

Route::middleware('auth')->group(function () {
    Volt::route('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');
});

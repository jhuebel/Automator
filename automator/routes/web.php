<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::middleware(['auth'])->group(function () {
    Volt::route('dashboard', 'pages.dashboard.index')
        ->name('dashboard');

    Volt::route('scripts', 'pages.scripts.index')
        ->middleware('can:scripts.view')
        ->name('scripts.index');

    Volt::route('scripts/create', 'pages.scripts.form')
        ->middleware('can:scripts.edit')
        ->name('scripts.create');

    Volt::route('scripts/{script}/edit', 'pages.scripts.form')
        ->middleware('can:scripts.edit')
        ->name('scripts.edit');

    Volt::route('runner', 'pages.runner.index')
        ->middleware('can:scripts.run')
        ->name('runner.index');

    Volt::route('history', 'pages.history.index')
        ->name('history.index');

    Volt::route('jobs', 'pages.jobs.index')
        ->middleware('can:jobs.view')
        ->name('jobs.index');

    Volt::route('help', 'pages.help.index')
        ->name('help.index');

    Volt::route('settings', 'pages.settings.index')
        ->middleware('can:settings.manage')
        ->name('settings.index');

    Route::view('my-account', 'account')
        ->name('account.index');
});

require __DIR__.'/auth.php';

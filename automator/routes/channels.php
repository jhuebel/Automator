<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('execution.{executionId}', function ($user) {
    return $user->can('scripts.run');
});

Broadcast::channel('claude.completion.{requestId}', function ($user) {
    return $user->can('scripts.edit');
});

// Runners authenticate with a Sanctum bearer token, not a browser session.
Broadcast::channel('runner.{runnerId}', function ($runner, $runnerId) {
    return $runner instanceof \App\Models\Runner && (string) $runner->id === $runnerId;
}, ['guards' => ['sanctum']]);

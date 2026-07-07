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

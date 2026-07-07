<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Wraps a ShouldBroadcastNow event so it can be dispatched with a queue
 * delay — PendingBroadcast (the `broadcast()` helper's return value) has no
 * delay() method, but a plain queued job does.
 */
class BroadcastDelayedEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(public object $event) {}

    public function handle(): void
    {
        broadcast($this->event);
    }
}

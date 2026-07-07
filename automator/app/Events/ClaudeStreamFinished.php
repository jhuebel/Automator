<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ClaudeStreamFinished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $requestId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("claude.completion.{$this->requestId}")];
    }

    public function broadcastAs(): string
    {
        return 'finished';
    }
}

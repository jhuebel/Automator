<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ClaudeStreamFailed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $requestId,
        public string $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("claude.completion.{$this->requestId}")];
    }

    public function broadcastAs(): string
    {
        return 'failed';
    }

    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}

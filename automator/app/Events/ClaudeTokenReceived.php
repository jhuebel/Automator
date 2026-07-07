<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ClaudeTokenReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $requestId,
        public string $text,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("claude.completion.{$this->requestId}")];
    }

    public function broadcastAs(): string
    {
        return 'token';
    }

    public function broadcastWith(): array
    {
        return ['text' => $this->text];
    }
}

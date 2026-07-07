<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ScriptOutputReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $executionId,
        public string $text,
        public bool $isError,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("execution.{$this->executionId}")];
    }

    public function broadcastAs(): string
    {
        return 'output-line';
    }

    public function broadcastWith(): array
    {
        return [
            'text' => $this->text,
            'isError' => $this->isError,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

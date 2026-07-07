<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ScriptExecutionFinished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $executionId,
        public ?int $exitCode,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("execution.{$this->executionId}")];
    }

    public function broadcastAs(): string
    {
        return 'finished';
    }

    public function broadcastWith(): array
    {
        return ['exitCode' => $this->exitCode];
    }
}

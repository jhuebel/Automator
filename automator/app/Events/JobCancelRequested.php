<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class JobCancelRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $runnerId,
        public string $executionId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("runner.{$this->runnerId}")];
    }

    public function broadcastAs(): string
    {
        return 'job.cancel';
    }

    public function broadcastWith(): array
    {
        return ['execution_id' => $this->executionId];
    }
}

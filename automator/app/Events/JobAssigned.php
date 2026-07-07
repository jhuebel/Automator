<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class JobAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $runnerId,
        public string $executionId,
        public string $language,
        public string $content,
        public array $variables,
        public int $timeoutSeconds,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("runner.{$this->runnerId}")];
    }

    public function broadcastAs(): string
    {
        return 'job.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->executionId,
            'language' => $this->language,
            'content' => $this->content,
            // An empty PHP array encodes as JSON `[]`, not `{}` — force object
            // notation so the runner's `map[string]string` unmarshal doesn't choke.
            'variables' => empty($this->variables) ? new \stdClass : $this->variables,
            'timeout_seconds' => $this->timeoutSeconds,
        ];
    }
}

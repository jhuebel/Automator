<?php

namespace App\Services;

use App\Events\JobAssigned;
use App\Events\JobCancelRequested;
use App\Events\ScriptExecutionFinished;
use App\Jobs\BroadcastDelayedEvent;
use App\Models\AppSetting;
use App\Models\Runner;
use App\Models\ScriptExecutionResult;

class RunnerAssignmentService
{
    /**
     * Assign a pending execution to the least-busy eligible runner and push
     * the job to it. If no runner is eligible, the execution is marked
     * failed immediately — there is no local fallback and no silent retry.
     */
    public function assign(ScriptExecutionResult $result, ?array $requiredTags = null): void
    {
        $runner = $this->pickRunner($requiredTags);

        if (! $runner) {
            $this->failNoRunner($result, $requiredTags);

            return;
        }

        $runner->increment('current_job_count');

        $result->update(['runner_id' => $runner->id]);

        $script = $result->script;
        $variables = collect($script?->variables ?? [])
            ->filter(fn ($v) => filled($v['name'] ?? null))
            ->mapWithKeys(fn ($v) => [$v['name'] => $v['default_value'] ?? ''])
            ->all();

        // Small delay so the browser's Echo subscription (started once the
        // Livewire response reaches the client) is in place before the runner
        // starts producing output — Reverb doesn't replay history to late
        // subscribers. Requires a queue worker to process the delayed broadcast.
        BroadcastDelayedEvent::dispatch(new JobAssigned(
            $runner->id,
            $result->id,
            $result->language->value,
            $script?->content ?? '',
            $variables,
            AppSetting::current()->execution_timeout_seconds,
        ))->delay(now()->addMilliseconds(600));
    }

    public function requestCancel(ScriptExecutionResult $result): void
    {
        $result->update(['cancel_requested_at' => now()]);

        if ($result->runner_id) {
            JobCancelRequested::dispatch($result->runner_id, $result->id);
        }
    }

    private function pickRunner(?array $requiredTags): ?Runner
    {
        return Runner::query()
            ->where('status', 'online')
            ->whereColumn('current_job_count', '<', 'max_concurrent_jobs')
            ->get()
            ->filter(fn (Runner $runner) => $runner->satisfiesTags($requiredTags))
            ->sortBy([
                ['current_job_count', 'asc'],
                ['last_seen_at', 'desc'],
            ])
            ->first();
    }

    private function failNoRunner(ScriptExecutionResult $result, ?array $requiredTags): void
    {
        $message = empty($requiredTags)
            ? 'No available runner is currently online.'
            : 'No available runner matched the required tags: '.implode(', ', $requiredTags).'.';

        $result->update([
            'exit_code' => -1,
            'completed_at' => now(),
            'output' => [
                ['text' => $message, 'is_error' => true, 'timestamp' => now()->toIso8601String()],
            ],
        ]);

        ScriptExecutionFinished::dispatch($result->id, -1);
    }
}

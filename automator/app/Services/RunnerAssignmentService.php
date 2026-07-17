<?php

namespace App\Services;

use App\Enums\ScriptLanguage;
use App\Events\JobAssigned;
use App\Events\JobCancelRequested;
use App\Events\ScriptExecutionFinished;
use App\Jobs\BroadcastDelayedEvent;
use App\Models\AppSetting;
use App\Models\Runner;
use App\Models\RunnerGroup;
use App\Models\ScriptExecutionResult;

class RunnerAssignmentService
{
    /**
     * Assign a pending execution to a runner and push the job to it. Only
     * runners that reported the execution's language as available in their
     * last heartbeat are eligible (Runner::supportsLanguage()). If
     * $preferredRunnerId is given, that specific runner is used (and
     * $requiredTags is ignored — an explicit pick overrides tag routing).
     * Otherwise, if $preferredRunnerGroupId is given, the least-busy
     * eligible runner within that group is picked (still honoring
     * $requiredTags — targeting a group is a scoped auto-pick, not an
     * explicit single-runner override). Otherwise the least-busy eligible
     * runner fleet-wide is picked automatically. If no runner is eligible,
     * the execution is marked failed immediately — there is no local
     * fallback and no silent retry.
     */
    public function assign(ScriptExecutionResult $result, ?array $requiredTags = null, ?string $preferredRunnerId = null, ?string $preferredRunnerGroupId = null): void
    {
        $language = $result->language;

        $runner = match (true) {
            (bool) $preferredRunnerId => $this->pickSpecificRunner($preferredRunnerId, $language),
            (bool) $preferredRunnerGroupId => $this->pickFromGroup($preferredRunnerGroupId, $requiredTags, $language),
            default => $this->pickRunner($requiredTags, $language),
        };

        if (! $runner) {
            $this->failNoRunner($result, $requiredTags, $preferredRunnerId, $preferredRunnerGroupId, $language);

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

    private function pickRunner(?array $requiredTags, ScriptLanguage $language): ?Runner
    {
        return Runner::query()
            ->where('status', 'online')
            ->whereColumn('current_job_count', '<', 'max_concurrent_jobs')
            ->get()
            ->filter(fn (Runner $runner) => $runner->satisfiesTags($requiredTags) && $runner->supportsLanguage($language))
            ->sortBy([
                ['current_job_count', 'asc'],
                ['last_seen_at', 'desc'],
            ])
            ->first();
    }

    private function pickSpecificRunner(string $runnerId, ScriptLanguage $language): ?Runner
    {
        $runner = Runner::query()
            ->whereKey($runnerId)
            ->where('status', 'online')
            ->whereColumn('current_job_count', '<', 'max_concurrent_jobs')
            ->first();

        return $runner && $runner->supportsLanguage($language) ? $runner : null;
    }

    private function pickFromGroup(string $groupId, ?array $requiredTags, ScriptLanguage $language): ?Runner
    {
        $group = RunnerGroup::with('runners')->find($groupId);

        return $group?->runners
            ->filter(fn (Runner $runner) => $runner->isOnline() && $runner->hasCapacity()
                && $runner->satisfiesTags($requiredTags) && $runner->supportsLanguage($language))
            ->sortBy([
                ['current_job_count', 'asc'],
                ['last_seen_at', 'desc'],
            ])
            ->first();
    }

    private function failNoRunner(ScriptExecutionResult $result, ?array $requiredTags, ?string $preferredRunnerId, ?string $preferredRunnerGroupId, ScriptLanguage $language): void
    {
        if ($preferredRunnerId) {
            $runner = Runner::find($preferredRunnerId);
            $message = match (true) {
                ! $runner => 'The selected runner no longer exists.',
                ! $runner->isOnline() || ! $runner->hasCapacity() => "The selected runner (\"{$runner->name}\") is offline or at capacity.",
                ! $runner->supportsLanguage($language) => "The selected runner (\"{$runner->name}\") does not report {$language->label()} as available.",
                default => "The selected runner (\"{$runner->name}\") is not available.",
            };
        } elseif ($preferredRunnerGroupId) {
            $group = RunnerGroup::find($preferredRunnerGroupId);
            $message = $group
                ? "No runner in the \"{$group->name}\" group is currently online with capacity that reports {$language->label()} as available."
                : 'The selected runner group no longer exists.';
        } else {
            $onlineWithCapacity = Runner::query()
                ->where('status', 'online')
                ->whereColumn('current_job_count', '<', 'max_concurrent_jobs')
                ->get();
            $tagMatches = $onlineWithCapacity->filter(fn (Runner $runner) => $runner->satisfiesTags($requiredTags));

            $message = match (true) {
                $onlineWithCapacity->isEmpty() => 'No available runner is currently online.',
                $tagMatches->isEmpty() => 'No available runner matched the required tags: '.implode(', ', $requiredTags).'.',
                default => "No available runner reports {$language->label()} as available.",
            };
        }

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

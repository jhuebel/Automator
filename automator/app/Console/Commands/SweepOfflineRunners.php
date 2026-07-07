<?php

namespace App\Console\Commands;

use App\Events\ScriptExecutionFinished;
use App\Models\Runner;
use App\Models\ScriptExecutionResult;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automator:sweep-offline-runners')]
#[Description('Mark runners offline after missing heartbeats, and fail any of their orphaned in-flight executions')]
class SweepOfflineRunners extends Command
{
    /**
     * Runners heartbeat every ~15s; missing 3 in a row is a reasonable
     * liveness threshold without being trigger-happy on a single slow tick.
     */
    private const HEARTBEAT_INTERVAL_SECONDS = 15;

    private const MISSED_HEARTBEATS_ALLOWED = 3;

    public function handle(): void
    {
        $threshold = now()->subSeconds(self::HEARTBEAT_INTERVAL_SECONDS * self::MISSED_HEARTBEATS_ALLOWED);

        $staleRunners = Runner::query()
            ->where('status', 'online')
            ->where(function ($q) use ($threshold) {
                $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $threshold);
            })
            ->get();

        foreach ($staleRunners as $runner) {
            $runner->update(['status' => 'offline', 'current_job_count' => 0]);
            $this->warn("Runner '{$runner->name}' marked offline (last seen: ".($runner->last_seen_at?->diffForHumans() ?? 'never').').');

            $orphaned = ScriptExecutionResult::query()
                ->where('runner_id', $runner->id)
                ->whereNull('completed_at')
                ->get();

            foreach ($orphaned as $execution) {
                $execution->update([
                    'exit_code' => -1,
                    'completed_at' => now(),
                    'output' => [
                        ...$execution->output,
                        ['text' => "Runner '{$runner->name}' disconnected; execution marked as failed.", 'is_error' => true, 'timestamp' => now()->toIso8601String()],
                    ],
                ]);

                ScriptExecutionFinished::dispatch($execution->id, -1);

                $this->warn("  orphaned execution {$execution->id} marked failed.");
            }
        }

        if ($staleRunners->isEmpty()) {
            $this->info('No stale runners.');
        }
    }
}

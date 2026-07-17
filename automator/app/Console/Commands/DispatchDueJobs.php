<?php

namespace App\Console\Commands;

use App\Models\ScheduledJob;
use App\Models\ScriptExecutionResult;
use App\Services\RunnerAssignmentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automator:dispatch-due-jobs')]
#[Description('Dispatch scheduled jobs whose next run time is due, and record completions of in-flight runs')]
class DispatchDueJobs extends Command
{
    public function __construct(private RunnerAssignmentService $assignment)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        // Must run before the due-jobs query below: it advances next_run_at for any
        // job whose previous execution just finished. Otherwise next_run_at would
        // stay stuck in the past and the job would be re-dispatched every tick.
        $this->recordCompletions();

        $due = ScheduledJob::query()
            ->where('is_enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->get();

        foreach ($due as $job) {
            if ($job->current_execution_id) {
                $current = ScriptExecutionResult::find($job->current_execution_id);
                if ($current && $current->completed_at === null) {
                    $this->warn("Skipping '{$job->name}' — previous run still in progress.");

                    continue;
                }
            }

            $script = $job->script;
            if (! $script) {
                continue;
            }

            $result = ScriptExecutionResult::create([
                'script_id' => $script->id,
                'script_name' => $script->name,
                'language' => $script->language,
                'started_at' => now(),
                'output' => [],
            ]);

            $this->assignment->assign($result, $job->required_runner_tags, $job->preferred_runner_id, $job->preferred_runner_group_id);

            $job->update(['current_execution_id' => $result->id]);

            $this->info("Dispatched '{$job->name}'.");
        }
    }

    /**
     * Advance last_run_at/last_exit_code/next_run_at for jobs whose in-flight
     * execution has finished since the previous tick.
     */
    private function recordCompletions(): void
    {
        ScheduledJob::query()
            ->whereNotNull('current_execution_id')
            ->get()
            ->each(function (ScheduledJob $job) {
                $execution = ScriptExecutionResult::find($job->current_execution_id);

                if (! $execution || $execution->completed_at === null) {
                    return;
                }

                $job->last_run_at = $execution->completed_at;
                $job->last_exit_code = $execution->exit_code;
                $job->current_execution_id = null;
                $job->refreshNextRunAt();
                $job->save();
            });
    }
}

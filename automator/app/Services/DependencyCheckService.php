<?php

namespace App\Services;

use App\Models\Runner;
use Illuminate\Support\Facades\DB;

class DependencyCheckService
{
    /**
     * Script runtime availability per registered runner, as last reported in
     * that runner's heartbeat. The Laravel management plane never executes
     * scripts itself, so its own PATH is irrelevant here — only runner hosts
     * matter.
     *
     * @return array{id: string, name: string, status: string, os: ?string, last_seen_at: ?string, runtimes: array}[]
     */
    public function runnerRuntimes(): array
    {
        return Runner::orderBy('name')->get()
            ->map(fn (Runner $runner) => [
                'id' => $runner->id,
                'name' => $runner->name,
                'status' => $runner->status,
                'os' => $runner->os,
                'last_seen_at' => $runner->last_seen_at?->toIso8601String(),
                'runtimes' => $runner->runtimes ?? [],
            ])
            ->all();
    }

    /**
     * @return array{driver: string, database: ?string, connected: bool, error: ?string, script_count: int, job_count: int, history_count: int}
     */
    public function databaseStatus(): array
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        try {
            DB::connection()->getPdo();

            return [
                'driver' => $connection,
                'database' => is_string($database) ? $database : null,
                'connected' => true,
                'error' => null,
                'script_count' => \App\Models\ScriptDefinition::count(),
                'job_count' => \App\Models\ScheduledJob::count(),
                'history_count' => \App\Models\ScriptExecutionResult::count(),
            ];
        } catch (\Throwable $e) {
            return [
                'driver' => $connection,
                'database' => is_string($database) ? $database : null,
                'connected' => false,
                'error' => $e->getMessage(),
                'script_count' => 0,
                'job_count' => 0,
                'history_count' => 0,
            ];
        }
    }

    /**
     * @return array{php_version: string, laravel_version: string, os: string, queue_driver: string, server_time: string}
     */
    public function appInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'os' => PHP_OS_FAMILY,
            'queue_driver' => config('queue.default'),
            'server_time' => now()->format('Y-m-d H:i:s T'),
        ];
    }
}

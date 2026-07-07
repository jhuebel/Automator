<?php

namespace App\Services;

use App\Enums\ScriptLanguage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class DependencyCheckService
{
    /**
     * @return array{name: string, description: string, language: ?ScriptLanguage, command: string, version_args: string}[]
     */
    private const RUNTIME_CHECKS = [
        ['name' => 'Bash', 'description' => 'Bourne Again Shell', 'command' => 'bash', 'version_args' => '--version', 'language' => ScriptLanguage::Bash],
        ['name' => 'PowerShell Core', 'description' => 'Cross-platform PowerShell (pwsh)', 'command' => 'pwsh', 'version_args' => '--version', 'language' => ScriptLanguage::PowerShell],
        ['name' => 'Python 3', 'description' => 'Python interpreter', 'command' => 'python3', 'version_args' => '--version', 'language' => ScriptLanguage::Python],
        ['name' => 'Ansible', 'description' => 'Ansible automation platform', 'command' => 'ansible-playbook', 'version_args' => '--version', 'language' => ScriptLanguage::Ansible],
        ['name' => 'Terraform', 'description' => 'Infrastructure as Code tool', 'command' => 'terraform', 'version_args' => 'version', 'language' => ScriptLanguage::Terraform],
    ];

    /**
     * @return array{name: string, description: string, available: bool, version: ?string, path: ?string, error: ?string, language: ScriptLanguage}[]
     */
    public function checkRuntimes(): array
    {
        return array_map(
            fn (array $check) => $this->checkRuntime(...$check),
            self::RUNTIME_CHECKS,
        );
    }

    private function checkRuntime(string $name, string $description, string $command, string $version_args, ScriptLanguage $language): array
    {
        try {
            $path = $this->resolvePath($command);

            $process = new Process([$command, ...explode(' ', $version_args)]);
            $process->setTimeout(5);
            $process->run();

            $output = $process->getOutput().$process->getErrorOutput();
            $version = collect(preg_split('/\r?\n/', trim($output)))->first();

            return [
                'name' => $name,
                'description' => $description,
                'available' => $process->isSuccessful() || $path !== null,
                'version' => $version ?: null,
                'path' => $path,
                'error' => null,
                'language' => $language,
            ];
        } catch (ProcessTimedOutException) {
            return ['name' => $name, 'description' => $description, 'available' => false, 'version' => null, 'path' => null, 'error' => 'Timed out after 5s', 'language' => $language];
        } catch (\Throwable $e) {
            return ['name' => $name, 'description' => $description, 'available' => false, 'version' => null, 'path' => null, 'error' => 'Not found in PATH', 'language' => $language];
        }
    }

    private function resolvePath(string $command): ?string
    {
        try {
            $process = new Process(['which', $command]);
            $process->setTimeout(5);
            $process->run();

            return $process->isSuccessful() ? trim($process->getOutput()) ?: null : null;
        } catch (\Throwable) {
            return null;
        }
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

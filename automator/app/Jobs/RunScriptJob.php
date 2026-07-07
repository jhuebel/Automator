<?php

namespace App\Jobs;

use App\Enums\ScriptLanguage;
use App\Events\ScriptExecutionFinished;
use App\Events\ScriptOutputReceived;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\ScriptExecutionResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class RunScriptJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Safety-net ceiling above the configurable per-script timeout. */
    public int $timeout = 3600;

    private array $outputBuffer = [];

    private float $lastFlushAt = 0;

    public function __construct(
        public string $executionId,
        public array $variables = [],
    ) {
        $this->onQueue('executions');
    }

    public function handle(): void
    {
        $result = ScriptExecutionResult::with('script')->findOrFail($this->executionId);
        $script = $result->script;

        if (! $script) {
            $this->finish($result, -1);

            return;
        }

        AuditLog::record('Script.Executed', $script->name, 'started', $result->username);

        try {
            $exitCode = $script->language === ScriptLanguage::Terraform
                ? $this->runTerraform($result, $script)
                : $this->runScript($result, $script);
        } catch (Throwable $e) {
            Log::error('Script execution failed', ['execution_id' => $this->executionId, 'error' => $e->getMessage()]);
            $this->appendLine($result, "Execution error: {$e->getMessage()}", true);
            $exitCode = -1;
        }

        $this->finish($result, $exitCode, $script->name);
    }

    private function runScript(ScriptExecutionResult $result, $script): int
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'automator_').$script->language->fileExtension();
        file_put_contents($tempFile, $script->content);

        try {
            $process = new Process($script->language->commandFor($tempFile));
            $process->setTimeout(AppSetting::current()->execution_timeout_seconds);
            $process->setEnv($this->processEnv());

            return $this->runProcess($process, $result);
        } finally {
            @unlink($tempFile);
        }
    }

    private function runTerraform(ScriptExecutionResult $result, $script): int
    {
        $tempDir = sys_get_temp_dir().'/automator_'.$this->executionId.'_tf';
        mkdir($tempDir, recursive: true);
        file_put_contents($tempDir.'/main.tf', $script->content);

        $tfEnv = [];
        foreach ($this->variables as $key => $value) {
            if (trim((string) $key) !== '') {
                $tfEnv["TF_VAR_{$key}"] = $value;
            }
        }

        try {
            $this->appendLine($result, '==> terraform init', false);
            $init = new Process(['terraform', 'init', '-no-color'], $tempDir);
            $init->setTimeout(AppSetting::current()->execution_timeout_seconds);
            $init->setEnv($tfEnv);
            $initExit = $this->runProcess($init, $result);

            if ($initExit !== 0) {
                return $initExit;
            }

            $this->appendLine($result, '==> terraform apply', false);
            $apply = new Process(['terraform', 'apply', '-auto-approve', '-no-color'], $tempDir);
            $apply->setTimeout(AppSetting::current()->execution_timeout_seconds);
            $apply->setEnv($tfEnv);

            return $this->runProcess($apply, $result);
        } finally {
            (new Process(['rm', '-rf', $tempDir]))->run();
        }
    }

    private function runProcess(Process $process, ScriptExecutionResult $result): int
    {
        $buffers = ['out' => '', 'err' => ''];

        // The output callback must be registered via start() rather than passed to
        // wait() later: any status-inspecting call in between (e.g. getPid()) makes
        // Symfony Process read pending pipe data into its own internal buffer, which
        // would silently vanish from a callback attached only afterward via wait().
        $onOutput = function (string $type, string $chunk) use (&$buffers, $result) {
            $key = $type === Process::ERR ? 'err' : 'out';
            $buffers[$key] .= $chunk;

            while (($pos = strpos($buffers[$key], "\n")) !== false) {
                $line = substr($buffers[$key], 0, $pos);
                $buffers[$key] = substr($buffers[$key], $pos + 1);
                $this->appendLine($result, $line, $key === 'err');
            }
        };

        try {
            $process->start($onOutput);
            $result->update(['pid' => $process->getPid()]);
            $process->wait();

            foreach ($buffers as $key => $remainder) {
                if ($remainder !== '') {
                    $this->appendLine($result, $remainder, $key === 'err');
                }
            }

            return $process->getExitCode() ?? -1;
        } catch (ProcessTimedOutException) {
            $this->appendLine($result, 'Execution timed out.', true);

            return -1;
        }
    }

    private function processEnv(): array
    {
        $env = [];
        foreach ($this->variables as $key => $value) {
            if (trim((string) $key) !== '') {
                $env[$key] = $value;
            }
        }

        return $env;
    }

    private function appendLine(ScriptExecutionResult $result, string $text, bool $isError): void
    {
        broadcast(new ScriptOutputReceived($this->executionId, $text, $isError));

        $this->outputBuffer[] = ['text' => $text, 'is_error' => $isError, 'timestamp' => now()->toIso8601String()];

        $now = microtime(true);
        if ($now - $this->lastFlushAt > 0.25) {
            $this->flush($result);
            $this->lastFlushAt = $now;
        }
    }

    private function flush(ScriptExecutionResult $result): void
    {
        if (empty($this->outputBuffer)) {
            return;
        }

        $result->update(['output' => [...$result->output, ...$this->outputBuffer]]);
        $this->outputBuffer = [];
    }

    private function finish(ScriptExecutionResult $result, ?int $exitCode, ?string $scriptName = null): void
    {
        $this->flush($result);

        $result->update([
            'exit_code' => $exitCode,
            'completed_at' => now(),
            'pid' => null,
        ]);

        $outcome = $exitCode === 0 ? "exit {$exitCode}" : 'failed (exit '.($exitCode ?? 'null').')';
        AuditLog::record('Script.Executed', $scriptName ?? $result->script_name, $outcome, $result->username);

        broadcast(new ScriptExecutionFinished($this->executionId, $exitCode));
    }
}

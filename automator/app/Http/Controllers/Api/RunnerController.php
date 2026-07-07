<?php

namespace App\Http\Controllers\Api;

use App\Events\ScriptExecutionFinished;
use App\Events\ScriptOutputReceived;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Runner;
use App\Models\RunnerEnrollmentToken;
use App\Models\ScriptExecutionResult;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RunnerController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'name' => 'required|string|max:255|unique:runners,name',
            'hostname' => 'nullable|string|max:255',
            'os' => 'nullable|string|in:linux,windows',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
        ]);

        $enrollmentToken = RunnerEnrollmentToken::redeem($validated['token']);

        if (! $enrollmentToken) {
            return response()->json([
                'message' => 'The enrollment token is invalid, expired, or already used.',
            ], 422);
        }

        $runner = Runner::create([
            'name' => $validated['name'],
            'hostname' => $validated['hostname'] ?? null,
            'os' => $validated['os'] ?? null,
            'tags' => $validated['tags'] ?? [],
            'status' => 'online',
            'last_seen_at' => now(),
        ]);

        $token = $runner->createToken('runner', ['runner']);
        $runner->forceFill(['personal_access_token_id' => $token->accessToken->id])->save();

        $reverb = config('broadcasting.connections.reverb');

        return response()->json([
            'runner_id' => $runner->id,
            'token' => $token->plainTextToken,
            'reverb' => [
                'key' => $reverb['key'],
                'host' => $reverb['options']['host'],
                'port' => (int) $reverb['options']['port'],
                'scheme' => $reverb['options']['scheme'],
            ],
        ], 201);
    }

    public function unregister(Request $request)
    {
        $runner = $request->user();
        $runner->tokens()->delete();
        $runner->delete();

        return response()->json(['status' => 'ok']);
    }

    public function heartbeat(Request $request)
    {
        $validated = $request->validate([
            'runtimes' => 'nullable|array',
            'runtimes.*.name' => 'required_with:runtimes|string',
            'runtimes.*.description' => 'nullable|string',
            'runtimes.*.available' => 'boolean',
            'runtimes.*.version' => 'nullable|string',
            'runtimes.*.path' => 'nullable|string',
            'runtimes.*.error' => 'nullable|string',
        ]);

        $runner = $request->user();

        if (array_key_exists('runtimes', $validated)) {
            $runner->runtimes = $validated['runtimes'];
        }

        $runner->markSeen();

        return response()->json(['status' => 'ok']);
    }

    public function output(Request $request, ScriptExecutionResult $execution)
    {
        $this->authorizeOwnership($request, $execution);

        // Laravel's global ConvertEmptyStringsToNull middleware turns a blank
        // "text" value into null before validation ever sees it — a blank line
        // is legitimate script output, so this must accept null, not reject it.
        $validated = $request->validate([
            'lines' => 'present|array',
            'lines.*.text' => 'nullable|string',
            'lines.*.is_error' => 'boolean',
            'lines.*.timestamp' => 'nullable|string',
        ]);

        $lines = collect($validated['lines'])->map(fn ($line) => [
            'text' => $line['text'] ?? '',
            'is_error' => (bool) ($line['is_error'] ?? false),
            'timestamp' => $line['timestamp'] ?? now()->toIso8601String(),
        ]);

        $execution->update(['output' => [...$execution->output, ...$lines->all()]]);

        $lines->each(fn ($line) => broadcast(
            new ScriptOutputReceived($execution->id, $line['text'], $line['is_error'])
        ));

        $request->user()->markSeen();

        return response()->json(['status' => 'ok']);
    }

    public function finish(Request $request, ScriptExecutionResult $execution)
    {
        $this->authorizeOwnership($request, $execution);

        $validated = $request->validate([
            'exit_code' => 'required|integer',
        ]);

        $execution->update([
            'exit_code' => $validated['exit_code'],
            'completed_at' => now(),
        ]);

        $runner = $request->user();
        $runner->decrement('current_job_count');
        $runner->markSeen();

        $outcome = $execution->is_success ? "exit {$validated['exit_code']}" : "failed (exit {$validated['exit_code']})";
        AuditLog::record('Script.Executed', $execution->script_name, $outcome, $execution->username);

        broadcast(new ScriptExecutionFinished($execution->id, $validated['exit_code']));

        return response()->json(['status' => 'ok']);
    }

    private function authorizeOwnership(Request $request, ScriptExecutionResult $execution): void
    {
        if ($execution->runner_id !== $request->user()->id) {
            throw new AccessDeniedHttpException('This execution is not assigned to your runner.');
        }
    }
}

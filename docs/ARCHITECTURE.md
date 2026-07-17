# Architecture

Automator is split into two independently-deployable pieces: a **management plane**
(Laravel) that owns the UI, data, scheduling, and job assignment; and one or more
**runners** (standalone Go binaries) that execute scripts on Linux or Windows hosts. The
management plane never executes scripts itself — every run, including on a single-box
install, goes through a registered runner.

```
Browser  <--Reverb WS-->  Management Plane (Laravel)  <--Reverb WS (push)-->  Runner(s)
                                |    ^                                            |
                                |    |___________ HTTPS REST (register, __________|
                                |                  heartbeat, output, finish)
                                v
                             Database (SQLite/MySQL)
```

## Management plane

Laravel 13 app (`automator/`) using Livewire 3 + Volt for the UI, `spatie/laravel-permission`
for roles, Laravel Sanctum for runner authentication, and Laravel Reverb as the
WebSocket broadcasting server.

| Concern | Where |
|---|---|
| Page routes | `routes/web.php` (Volt single-file components under `resources/views/livewire/pages/`) |
| Runner REST API | `routes/api.php` → `app/Http/Controllers/Api/RunnerController.php` |
| Broadcast channel authorization | `routes/channels.php` |
| Scheduled-job dispatch tick | `routes/console.php` (Laravel's own scheduler, `everyMinute()`) |
| Runner offline sweep | its own systemd timer (`packaging/linux-common/automator-runner-sweep.timer`), independent of Laravel's scheduler |
| Domain models | `app/Models/` |
| Business logic that isn't CRUD | `app/Services/` (`RunnerAssignmentService`, `DependencyCheckService`) |
| Broadcast events | `app/Events/` |
| Background jobs (queue) | `app/Jobs/` |
| Scheduled artisan commands | `app/Console/Commands/` |

### Request flow: running a script

1. A Livewire component (`pages/runner/index.blade.php`) creates a `ScriptExecutionResult`
   row and calls `RunnerAssignmentService::assign()`, optionally passing a specific runner
   the user picked from the "Run" panel's runner dropdown (defaults to "Auto").
2. `RunnerAssignmentService` either uses that explicit runner (if given — this bypasses
   tag matching entirely) or picks the least-busy online `Runner` whose tags satisfy any
   required tags (`Runner::satisfiesTags()`). Either way, the runner must also report the
   script's language as available in its last heartbeat (`Runner::supportsLanguage()`) —
   a runner isn't eligible for a language it doesn't have installed, whether picked
   automatically or explicitly. Eligible runners increment `current_job_count` and get
   `runner_id` stamped on the execution. If no eligible runner exists (offline, at
   capacity, missing tags, or missing the language), the execution is immediately marked
   failed (`exit_code = -1`) with a message naming the specific reason — there is no
   queueing-and-waiting and no silent retry. Scheduled jobs can likewise be pinned to a
   specific runner via `ScheduledJob.preferred_runner_id`, set from the Scheduled Jobs UI;
   both the Run Script and Scheduled Jobs runner-picker dropdowns only list
   language-capable runners in the first place.
3. A `JobAssigned` event is broadcast on that runner's private Reverb channel
   (`runner.{runnerId}`), delayed ~600ms via `BroadcastDelayedEvent` (a `ShouldQueue`
   wrapper) so the browser's own Echo subscription — established once the Livewire
   response reaches the client — is in place before the runner starts producing output.
   Reverb does not replay history to late subscribers.
4. The runner executes the script and calls back over REST (`/api/runner/executions/{id}/output`
   and `.../finish`); the controller persists the output and re-broadcasts
   `ScriptOutputReceived`/`ScriptExecutionFinished` on `execution.{executionId}` — the same
   channel and event shape the browser has always consumed, regardless of which runner
   actually ran the script.
5. Cancellation is symmetric: the Runner page sets `cancel_requested_at` and
   `RunnerAssignmentService::requestCancel()` broadcasts `JobCancelRequested` on the
   runner's channel; the runner signals its own subprocess and reports completion through
   the same `.../finish` call as any other completion.

### Scheduling

`app/Console/Commands/DispatchDueJobs.php` runs every minute (`routes/console.php`). It
first reconciles `ScheduledJob.current_execution_id` against any execution that finished
since the last tick (advancing `next_run_at`), then creates a new `ScriptExecutionResult`
and calls `RunnerAssignmentService::assign()` for every due, enabled job whose previous
run isn't still in flight.

`app/Console/Commands/SweepOfflineRunners.php` runs every ~30s. A runner that misses 3
heartbeats (default 15s interval) is flipped to `offline`, and any of its in-flight
executions (`completed_at IS NULL`) are marked failed with a "runner disconnected" output
line — surfaced in History/the live terminal as an ordinary failure, no special UI needed.
There is no automatic reassignment to another runner (side effects like `terraform apply`
aren't safely retryable); re-running is a manual operator action.

### AI Assistant

Independent of script execution and unaffected by the runner split: `StreamClaudeCompletionJob`
(a normal queued job, run on the management plane's own `default` queue) calls the
Anthropic Messages API directly with `stream: true`, parses the SSE stream, and broadcasts
`ClaudeTokenReceived`/`ClaudeStreamFinished`/`ClaudeStreamFailed` on `claude.completion.{requestId}`.
The frontend's `ai-assistant.js` consumes this the same way `script-terminal.js` consumes
script output.

## Runners

Standalone Go module (`runner/`), one binary (`automator-runner`) with four subcommands:

| Command | Purpose |
|---|---|
| `automator-runner register --server <url> --token <token> --name <n> [--tags a,b,c]` | One-time enrollment; writes a local config file |
| `automator-runner run [--config path]` | The daemon loop: connect to Reverb, listen for jobs, execute, report |
| `automator-runner unregister [--config path]` | Revoke its own token and remove itself from the registry |
| `automator-runner version` | Print the compiled-in version and exit — standalone debugging aid |

| File | Responsibility |
|---|---|
| `register.go` / `main.go` | CLI subcommands |
| `config.go` | Local config file (`Config` struct, OS-appropriate default path, load/save) |
| `api.go` | REST client — register, heartbeat, channel auth, output, finish |
| `pusher.go` | Minimal hand-rolled Pusher-protocol client for the Reverb WS connection |
| `run.go` | Daemon entry point; heartbeat loop |
| `runtimes.go` | Detects installed script runtimes (`bash`, `pwsh`, `python3`, `ansible-playbook`, `terraform`) at startup, reported with every heartbeat |
| `version.go` | The runner binary's own `Version` const, bumped when shipping a runner-side change worth telling apart in the fleet |
| `diskspace_linux.go` / `diskspace_windows.go` | `diskSpace()` — free/total bytes on the filesystem backing `os.TempDir()`, re-checked every heartbeat tick |
| `update.go` | `maybeApplyUpdate()` — downloads, checksum-verifies, and applies a newer binary advertised in a heartbeat response, then exits for the process supervisor to relaunch it |
| `update_linux.go` / `update_windows.go` | `replaceExecutable()` — OS-specific in-place binary replacement (atomic rename on Linux; rename-aside-then-write on Windows, which can't overwrite a running `.exe`) |
| `executor.go` | Subprocess execution, output streaming/batching, timeout enforcement, cancellation bookkeeping; also `Idle()`, checked before applying a self-update so one never interrupts a running job |
| `language.go` | Maps a script language to its OS-specific command (mirrors `ScriptLanguage::commandFor()` on the Laravel side, using `runtime.GOOS` instead of `PHP_OS_FAMILY`) |
| `reporter.go` | Batches output lines (~250ms flush cadence) before POSTing them |
| `process_linux.go` / `process_windows.go` | `prepareProcess()` — Windows starts the child in its own process group (`CREATE_NEW_PROCESS_GROUP`) so it can be signaled independently |
| `terminate_linux.go` / `terminate_windows.go` | Graceful cancellation: `SIGTERM` on Linux; `CTRL_BREAK_EVENT` with a 5s grace period then a hard `TerminateProcess` fallback on Windows (Go's `Process.Kill()` has no soft-signal equivalent there) |

See [RUNNER_PROTOCOL.md](RUNNER_PROTOCOL.md) for the wire-level registration, REST, and
WebSocket protocol details.

## Frontend

Livewire/Volt renders server-side; a handful of Alpine.js components
(`resources/js/`) handle the parts that need direct DOM/WebSocket access:

| File | Purpose |
|---|---|
| `echo.js` | Configures Laravel Echo against Reverb (shared by the other components) |
| `script-terminal.js` | Subscribes to `execution.{id}`, renders streamed output lines and the finished/exit-code state |
| `ai-assistant.js` | Subscribes to `claude.completion.{id}`, renders streamed tokens |
| `code-editor.js` | CodeMirror integration, language mode per `ScriptLanguage::codeMirrorMode()` |
| `chart-widget.js` | Dashboard chart rendering |

## Deployment topology

- **Single box**: management plane + one runner on the same host. The install scripts
  (`packaging/ubuntu/install.sh`, `packaging/rhel/install.sh`) provision this
  automatically — generate an enrollment token, register a local runner tagged `linux`,
  and start it as a systemd service.
- **Fleet**: any number of additional Linux/Windows runners register against the same
  management plane from **Settings → Runners**, each independently tagged
  (`--tags windows,terraform`) for routing via `required_runner_tags` on scheduled jobs.

See [INSTALL.md](INSTALL.md) for the operational install/uninstall/configuration
reference and [DATA_MODEL.md](DATA_MODEL.md) for the full schema.

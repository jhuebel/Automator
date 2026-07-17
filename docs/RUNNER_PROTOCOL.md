# Runner Protocol Reference

How a runner enrolls, authenticates, and exchanges job/output data with the management
plane. This is the contract implemented by `runner/` (Go) against
`app/Http/Controllers/Api/RunnerController.php`, `routes/api.php`, and
`routes/channels.php` — useful if you're debugging a runner, writing an alternative
runner implementation, or auditing the security model.

## Authentication

Runners authenticate as Sanctum-guarded principals — but the `Runner` model, not `User`,
owns the token (`Runner` uses `Laravel\Sanctum\HasApiTokens` directly). There is no
session/cookie auth path for runners; every authenticated call sends:

```
Authorization: Bearer <sanctum-token>
```

Tokens are scoped with a single ability, `runner` (`createToken('runner', ['runner'])`),
though no endpoint currently discriminates on it — it exists as a marker for future
tightening. Revoking a runner (Settings → Runners → Delete, or `automator-runner
unregister`) deletes its Sanctum token, which invalidates every subsequent call
immediately.

## 1. Enrollment (one-time, out-of-band token)

Enrollment is a two-step exchange modeled on GitHub Actions/GitLab Runner's bootstrap
flow — a human-distributed, single-use token stands in for a long-lived credential the
runner obtains for itself.

1. An admin (or `php artisan automator:generate-runner-token --ttl=<minutes>`, used by
   the install scripts for unattended setup) creates a `runner_enrollment_tokens` row via
   `RunnerEnrollmentToken::issue()`. The plaintext token is shown/printed exactly once;
   only its SHA-256 hash is persisted. Default TTL is 60 minutes.
2. The runner calls `POST /api/runner/register` with that plaintext token. The server
   redeems it (`RunnerEnrollmentToken::redeem()` — must be unexpired and unused,
   and is marked used atomically on lookup), creates the `Runner` row, and mints a
   Sanctum token for it.

```
POST /api/runner/register
Content-Type: application/json

{
  "token": "<plaintext enrollment token>",
  "name": "my-runner",              // must be unique across runners
  "hostname": "worker-01",          // optional, informational
  "os": "linux",                    // "linux" | "windows", optional
  "tags": ["linux", "terraform"]    // optional, used for job routing
}
```

Success (`201`):

```json
{
  "runner_id": "01j...",
  "token": "<sanctum bearer token — save this, it is not retrievable later>",
  "reverb": {
    "key": "app-key",
    "host": "automator.example.com",
    "port": 8080,
    "scheme": "https"
  }
}
```

Failure (`422`) if the token is invalid, expired, or already used:

```json
{ "message": "The enrollment token is invalid, expired, or already used." }
```

The runner persists `{server, token, runner_id, tags, reverb}` to its local config file
(`runner/config.go`) — `/etc/automator-runner/config.json` (root) or
`~/.config/automator-runner/config.json` (non-root) on Linux, mode `0600`;
`%ProgramData%\AutomatorRunner\config.json` on Windows, ACL-restricted at install time.
This is the only place the bearer token is stored; there is no re-issuance endpoint — if
the config is lost, the runner must re-enroll with a fresh token under a new name (or the
old `Runner` row must be deleted first, since `name` is unique).

## 2. Job delivery (push, via Reverb)

Job assignment and cancellation are pushed over the same Reverb WebSocket server the
browser's live-output terminal already uses — runners are simply a second class of
private-channel subscriber. The runner implements a deliberately minimal subset of the
Pusher wire protocol (`runner/pusher.go`), not a full client library.

1. Connect: `wss://{reverb.host}:{reverb.port}/app/{reverb.key}?protocol=7&client=automator-runner&version=1.0`
   (`ws://` if `reverb.scheme` is `http`).
2. Server sends `pusher:connection_established` with a `socket_id`.
3. Runner calls `POST /broadcasting/auth` (Laravel's standard broadcasting auth
   endpoint — the same one the browser's Echo client uses, just bearer-authenticated
   instead of cookie-authenticated) with `{channel_name: "private-runner.{id}", socket_id}`
   to get a signed `auth` string. Channel authorization for `runner.{runnerId}` is
   registered with an explicit `sanctum` guard override in `routes/channels.php`:
   ```php
   Broadcast::channel('runner.{runnerId}', function ($runner, $runnerId) {
       return $runner instanceof \App\Models\Runner && (string) $runner->id === $runnerId;
   }, ['guards' => ['sanctum']]);
   ```
4. Runner sends `pusher:subscribe` with `{channel, auth}`.
5. Server confirms with `pusher_internal:subscription_succeeded`; the runner is now live.
6. Server sends `pusher:ping` periodically; the runner must reply `pusher:pong` or the
   connection is dropped.
7. Two application events arrive on this channel:

**`job.assigned`** — a script has been routed to this runner:

```json
{
  "execution_id": "01j...",
  "language": "Bash",
  "content": "<script body>",
  "variables": { "MY_VAR": "value" },
  "timeout_seconds": 300
}
```

`variables` is always a JSON object (an empty PHP array is force-cast to `stdClass` on
the Laravel side specifically so it never serializes as `[]`, which would break a Go
`map[string]string` unmarshal).

**`job.cancel`** — a previously-assigned execution should be terminated:

```json
{ "execution_id": "01j..." }
```

On disconnect, the runner reconnects with exponential backoff (2s → 30s cap,
`runner/pusher.go`'s `runWithReconnect`). There is no message queue/redelivery on
reconnect — a job pushed while disconnected is simply missed by that runner (the
management plane's offline-sweep will eventually reassign work away from a runner that
stops heartbeating; see [ARCHITECTURE.md](ARCHITECTURE.md#scheduling)).

## 3. Runner-initiated REST calls

All under `auth:sanctum` middleware except `register`.

| Method & path | Purpose | Body |
|---|---|---|
| `POST /api/runner/register` | Enrollment (see above) | see above |
| `POST /api/runner/heartbeat` | Liveness + runtime/capability inventory, every 15s | `{"runtimes": [{"name", "description", "available", "version", "path", "error"}, ...], "version", "arch", "disk_free_bytes", "disk_total_bytes"}` |
| `POST /api/runner/unregister` | Self-revoke: deletes the runner's own token and `Runner` row | none |
| `POST /api/runner/executions/{execution}/output` | Stream output lines | `{"lines": [{"text", "is_error", "timestamp"}, ...]}` |
| `POST /api/runner/executions/{execution}/finish` | Report completion | `{"exit_code": <int>}` |
| `GET /api/runner/releases/{release}/download` | Download a runner binary update | none — see [Self-update](#5-self-update) |

Every one of these also calls `Runner::markSeen()` (sets `last_seen_at = now()`,
`status = 'online'`), so any authenticated traffic — not just the dedicated heartbeat —
counts as liveness.

**Ownership check**: `output` and `finish` both call `authorizeOwnership()`, which
throws `403` unless `$execution->runner_id === $request->user()->id` — a runner cannot
report against an execution it wasn't assigned.

**Output line quirk**: `text` is validated `nullable|string`, not `required|string`,
because Laravel's global `ConvertEmptyStringsToNull` middleware turns a blank line into
`null` before validation ever sees it, and a blank output line is legitimate script
output (reconstructed as `''` server-side).

**Heartbeat runtime detection**: the runner probes for `bash`, `pwsh`, `python3`,
`ansible-playbook`, and `terraform` on its own `PATH` once at process start
(`runner/runtimes.go`), not on every heartbeat tick, and reports the same snapshot each
time — the installed toolchain on a host doesn't change minute to minute. This powers
both the Settings → System Status runtime-by-runner matrix and job assignment eligibility:
`RunnerAssignmentService` (management-plane side) excludes any runner that hasn't
reported the script's language as `available: true` from both automatic least-busy
selection and the runner-picker dropdowns on the Run Script and Scheduled Jobs pages —
see `App\Models\Runner::supportsLanguage()` and `App\Enums\ScriptLanguage::runtimeName()`
for the name mapping (e.g. `PowerShell` ↔ the reported `"PowerShell Core"`).

**Runner identity fields**: `version` (the `automator-runner` binary's own version,
`runner/version.go`) and `arch` (`runtime.GOARCH`) are detected once at startup like the
runtimes above — informational only, not currently used to gate assignment. `disk_free_bytes`/
`disk_total_bytes` (free/total space on the filesystem backing `os.TempDir()`, where script
content and Terraform working directories get written) are the one exception re-checked on
every heartbeat tick rather than once, since disk usage can genuinely change between ticks
in a way none of the other reported fields do. All four are purely diagnostic today — surfaced
in the Settings → Runners expandable row — not scheduling inputs like `runtimes` is.

**Finish semantics**: decrements `current_job_count`, sets `exit_code` + `completed_at`,
writes an `AuditLog` entry (`Script.Executed`), and broadcasts `ScriptExecutionFinished`
on `execution.{executionId}` for the browser. `exit_code` can be any integer the process
returned; the runner itself uses `-1` for its own infrastructure failures (execution
error, timeout) rather than a real process exit code.

## 4. Cancellation

1. Browser clicks Cancel → Livewire sets `ScriptExecutionResult.cancel_requested_at` and
   calls `RunnerAssignmentService::requestCancel()`.
2. `JobCancelRequested` is broadcast on `runner.{runnerId}` (see §2).
3. The runner looks up its in-memory `map[executionID]*exec.Cmd` (`executor.go`) and
   signals the process:
   - **Linux**: `SIGTERM` to the direct child.
   - **Windows**: the child was started in its own process group
     (`CREATE_NEW_PROCESS_GROUP`, `process_windows.go`), so the runner sends
     `CTRL_BREAK_EVENT` to that group, then falls back to a hard `TerminateProcess`
     (via Go's `Process.Kill()`) after a 5s grace period if the process hasn't exited —
     Windows has no signal equivalent to `SIGTERM`, so this two-phase approach is
     intentional, not a stopgap.
4. The runner reports the outcome through the *same* `.../finish` call as any other
   completion — there's no separate "cancelled" status; a cancelled run just shows up
   with whatever exit code the killed process (or the runner's own `-1`) produced.

## 5. Self-update

The management plane can track newer `automator-runner` builds and offer them to the
fleet through the same heartbeat channel used for liveness — no separate polling loop.

**Publishing and releasing a build** are deliberately two separate, CLI-driven steps:

1. `php artisan automator:publish-runner-binary <path> --os=<linux|windows> --arch=amd64
   --runner-version=<version>` — computes a sha256 checksum, copies the binary into private
   storage, and creates/updates a `runner_releases` row with `is_released = false`.
   `packaging/build.sh` calls this automatically for both cross-compiled binaries after
   every build, using the version baked into `runner/version.go` (the build host can't
   `exec()` the Windows `.exe` to ask it directly, so the version is passed in rather
   than shelled out for).
2. `php artisan automator:release-runner-binary <version> --os=<linux|windows>
   --arch=amd64` — the *only* action that flips `is_released` to `true`. A build a runner
   can ever be offered must have gone through this step explicitly; publishing alone
   never makes a build live. This also means a rollback is just releasing an older
   version again — `RunnerRelease::latestFor()` orders by `released_at`, not semver.

**Discovery**: if Settings → Runners' "Automatically offer runner updates" toggle
(`AppSetting.runner_auto_update_enabled`) is on, every heartbeat response checks
`RunnerRelease::latestFor($runner->os, $runner->arch)` for a released build that differs
from the version the runner just reported, and includes it:

```json
{
  "status": "ok",
  "update": {
    "version": "1.3.0",
    "checksum_sha256": "...",
    "size_bytes": 12345678,
    "download_url": "https://.../api/runner/releases/{id}/download"
  }
}
```

The `update` key is omitted entirely when the toggle is off, there's no eligible release,
or the runner is already current — the common case stays a one-line response.

**Applying the update** (`runner/update.go`), triggered whenever a heartbeat response
includes `update`:

1. Refuses if a job is currently running (`Executor.Idle()`) — retried on the next tick,
   never interrupts an in-flight script.
2. Downloads the binary from `download_url` with the runner's own bearer token (the same
   credential it already uses for every other authenticated call).
3. Verifies the downloaded bytes' sha256 against `checksum_sha256` — refuses and leaves
   the current binary untouched on any mismatch.
4. Replaces the running executable (`replaceExecutable`, OS-specific):
   - **Linux**: writes to a temp file in the same directory, `chmod 0755`, then
     `os.Rename()` over the current binary's path — safe even while this process is
     running from that inode, since the kernel keeps it alive until exit.
   - **Windows**: renames the running exe aside to `automator-runner.exe.old` (Windows
     won't allow overwriting an in-use `.exe` directly, but will allow renaming one),
     then writes the new bytes to the now-vacant path.
5. On success, exits cleanly (`os.Exit(0)`) — no in-process re-exec. The process
   supervisor relaunches it from the now-updated binary: `Restart=always` in the packaged
   Linux systemd unit, and NSSM's `AppExit Default Restart` (set by
   `packaging/runner/windows/install.ps1`) on Windows, which restarts on any exit code,
   not just crashes.

## Timeouts

`timeout_seconds` from `job.assigned` is enforced runner-side via
`context.WithTimeout(context.Background(), timeout)` wrapping the `exec.Cmd`
(`executor.go`). On expiry the runner reports a timeout output line and finishes with
`-1`. This is independent of the browser or management plane — a runner that's been
disconnected from Reverb for longer than its own timeout will still self-terminate the
process and hold the result until it can reach the REST API again (or until the
offline-sweep reassigns/orphans it from the management plane's side; see
`SweepOfflineRunners` in [ARCHITECTURE.md](ARCHITECTURE.md#scheduling)).

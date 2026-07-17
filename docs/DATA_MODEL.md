# Data Model

All application tables use ULID primary keys (`HasUlids`) except the framework's own
`users`, `personal_access_tokens`, and permission tables, which use auto-increment IDs.
SQLite is the default connection; MySQL/MariaDB works unchanged (see
[INSTALL.md](INSTALL.md#database)).

## Entity relationship overview

```
User ──< ScriptExecutionResult (username, free text — not a FK)
User ──roles/permissions (spatie/laravel-permission)

ScriptDefinition ──< ScriptExecutionResult (script_id, nullOnDelete)
ScriptDefinition ──< ScheduledJob (script_id, cascadeOnDelete)

Runner ──< ScriptExecutionResult (runner_id)
Runner ──1 personal_access_tokens (via Sanctum HasApiTokens)
Runner }o--o{ RunnerGroup (runner_group_runner pivot — many-to-many)

RunnerEnrollmentToken (standalone; consumed once by RunnerController::register())
RunnerRelease (standalone; offered to runners via the heartbeat response, see RUNNER_PROTOCOL.md#5-self-update)

ScheduledJob ──1 ScriptExecutionResult (current_execution_id, the in-flight run if any)

AppSetting (singleton row — AppSetting::current() firstOrCreate([]))
AuditLog (standalone append-only log)
```

## Tables

### `script_definitions`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `name` | string | |
| `description` | text, nullable | |
| `language` | string | backed by `App\Enums\ScriptLanguage` (`Bash`, `PowerShell`, `Python`, `Ansible`, `Terraform`) |
| `content` | longtext | script body; a single HCL file for Terraform |
| `tags` | json | free-form labels, filterable in the Script Library |
| `variables` | json | list of `{name, type, default_value, required}`; `type` ∈ `App\Enums\ScriptVariableType` (Text/Number/Boolean/List) |

### `script_execution_results`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `script_id` | ulid, FK → `script_definitions`, nullable, `nullOnDelete` | |
| `runner_id` | ulid, FK → `runners`, nullable, `nullOnDelete` | which runner executed (or is executing) this |
| `script_name` | string | denormalized snapshot — survives script deletion/rename |
| `language` | string | denormalized snapshot |
| `username` | string, nullable | who triggered it (null for scheduled runs) |
| `started_at` | timestamp | |
| `completed_at` | timestamp, nullable | null while running |
| `exit_code` | integer, nullable | `-1` is used for infrastructure failures (no runner available, runner disconnected, timeout) as well as genuine non-zero process exits |
| `output` | json | ordered list of `{text, is_error, timestamp}` |
| `pid` | integer, nullable | vestigial — no longer populated now that execution is remote; kept for backward-compatible reads of historical rows |
| `cancel_requested_at` | timestamp, nullable | set by `RunnerAssignmentService::requestCancel()`; the runner clears nothing here, it just races to call `.../finish` |

Model accessors: `isRunning` (`completed_at === null`), `isSuccess` (`exit_code === 0`),
`durationSeconds` (null while running).

### `scheduled_jobs`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `name` | string | |
| `script_id` | ulid, FK → `script_definitions`, `cascadeOnDelete` | |
| `cron_expression` | string | standard 5-field cron, evaluated in UTC (`dragonmantank/cron-expression`) |
| `is_enabled` | boolean | |
| `required_runner_tags` | json, nullable | superset-match against a runner's `tags`; null/empty = any online runner with capacity. Not currently exposed in the UI — superseded in practice by `preferred_runner_id` below, but still honored if set directly |
| `preferred_runner_id` | ulid, FK → `runners`, nullable, `nullOnDelete` | pin this job to one specific runner (set from the Scheduled Jobs UI); when set, `RunnerAssignmentService::assign()` ignores `required_runner_tags` entirely and uses this runner if it's online with spare capacity, or fails the run with a clear "selected runner is offline or at capacity" message otherwise. Null/empty means auto-assign (least-busy online runner) |
| `preferred_runner_group_id` | ulid, FK → `runner_groups`, nullable, `nullOnDelete` | pin this job to a runner group instead of a single runner; mutually exclusive with `preferred_runner_id` (the UI writes exactly one, nulling the other). `RunnerAssignmentService::assign()` picks the least-busy eligible member of the group — still honoring `required_runner_tags`, since targeting a group is a scoped auto-pick rather than an explicit single-runner override. Deleting the group nulls this column, so the job falls back to Auto |
| `last_run_at` / `next_run_at` | timestamp, nullable | `next_run_at` indexed — `DispatchDueJobs` queries on it |
| `last_exit_code` | integer, nullable | |
| `current_execution_id` | ulid, nullable | the in-flight execution, if any; used to skip overlapping runs and to reconcile completion on the next scheduler tick |

### `runners`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `name` | string, unique | operator-chosen at `register` time |
| `hostname` | string, nullable | reported by the runner (`os.Hostname()`) |
| `os` | string, nullable | `linux` or `windows` (`runtime.GOOS`) |
| `version` | string, nullable | the `automator-runner` binary's own version (`runner/version.go`'s `Version` const), reported on every heartbeat — lets an admin spot a runner still running old code after a runner-side fix ships |
| `arch` | string, nullable | CPU architecture (`runtime.GOARCH`, e.g. `amd64`/`arm64`), reported on every heartbeat |
| `disk_free_bytes` / `disk_total_bytes` | unsigned bigint, nullable | free/total space on the filesystem backing the runner's temp directory (`os.TempDir()` — where script content and Terraform working directories are written), re-checked on every heartbeat since it can change between ticks unlike the other runner-identity fields |
| `tags` | json | capability labels for routing (e.g. `["linux", "terraform"]`) |
| `runtimes` | json | last-heartbeat snapshot of `[{name, description, available, version, path, error}]` — see `runner/runtimes.go`. Drives both the Settings → System Status matrix and, via `Runner::supportsLanguage()` / `ScriptLanguage::runtimeName()`, which runners are eligible to run a script in a given language (a runner with no matching `available: true` entry is excluded from assignment and from the runner-picker dropdowns) |
| `status` | string | `online` / `offline` (heartbeat-driven) / `disabled` (manually toggled from Settings → Runners, excluded from assignment same as offline but distinguished for operator intent); deleting the runner and its token is how an admin permanently revokes one |
| `last_seen_at` | timestamp, nullable | bumped by `Runner::markSeen()` on every heartbeat *and* every other authenticated runner API call |
| `current_job_count` | unsigned int, default 0 | incremented on assignment, decremented on `.../finish` |
| `max_concurrent_jobs` | unsigned int, default 1 | per-runner capacity ceiling; always the DB default today — neither the `register` CLI command nor the Settings UI exposes a way to set it, so raising it currently requires a direct DB update |
| `personal_access_token_id` | FK → `personal_access_tokens`, nullable, `nullOnDelete` | lets the Settings UI look up/revoke the runner's Sanctum token |

`Runner` uses Sanctum's `HasApiTokens` trait directly (a non-`User` model), so a runner
authenticates as its own Sanctum-guarded principal — see
[RUNNER_PROTOCOL.md](RUNNER_PROTOCOL.md#authentication).

### `runner_enrollment_tokens`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `token_hash` | string, unique | SHA-256 of the one-time plaintext token; the plaintext itself is never stored, only shown once at generation time |
| `expires_at` | timestamp | default TTL 60 minutes (`RunnerEnrollmentToken::issue()`) |
| `used_at` | timestamp, nullable | enforces single-use — `redeem()` requires `whereNull('used_at')` |
| `created_by` | FK → `users`, nullable, `nullOnDelete` | null when issued via `php artisan automator:generate-runner-token` (unattended install) rather than the Settings UI |

### `runner_groups`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `name` | string, unique | operator-chosen, e.g. `"us-east"` or `"shared-terraform"` |
| `description` | text, nullable | free-form, e.g. what region/resources this group is near |

Managed from Settings → Runner Groups (`App\Livewire\Settings\RunnerGroupManagement`). A group's
eligibility for a language or set of required tags is the **union** across its members —
`RunnerGroup::supportsLanguage()`/`satisfiesTags()` return true if *any* member runner qualifies,
since an execution still ultimately runs on one concrete runner
(`RunnerAssignmentService::pickFromGroup()` then picks the least-busy eligible member). This is
purely a management-plane concept — the Go runner is never told about group membership.

### `runner_group_runner`

Pivot table for the many-to-many `Runner` ⟷ `RunnerGroup` relationship (a runner may belong to
several groups, e.g. a host serving both a region and a shared pool). No surrogate key — composite
primary key on `(runner_group_id, runner_id)`, both `cascadeOnDelete()` so deleting either side
cleans up membership automatically. No timestamps; membership doesn't need a history.

### `runner_releases`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `version` | string | e.g. `"1.3.0"` |
| `os` | string | `linux` / `windows` |
| `arch` | string | `amd64` / `arm64` |
| `checksum_sha256` | string | computed at publish time from the binary's actual bytes |
| `storage_path` | string | relative path on the `local` (private) disk — `runner-releases/{id}/automator-runner[.exe]` |
| `size_bytes` | unsigned bigint | |
| `is_released` | boolean, default `false` | the fleet-visibility gate — `automator:publish-runner-binary` always creates this `false`; only `automator:release-runner-binary` flips it `true`. A runner is never offered (and the download endpoint 404s for) an unreleased row |
| `released_at` | timestamp, nullable | set when `is_released` flips true; `RunnerRelease::latestFor()` orders by this, not semver, so republishing an older version and releasing it is how a rollback works |
| `created_by` | FK → `users`, nullable, `nullOnDelete` | null for CLI-published rows (the normal case — `packaging/build.sh` publishes every build automatically) |

Unique on `(version, os, arch)` — republishing the same tuple overwrites the existing row
(and resets `is_released` to `false`, requiring re-release) rather than creating a
duplicate.

### `app_settings`

Singleton table — `AppSetting::current()` calls `firstOrCreate([])`, so there is always
exactly one row (id `1`).

| Column | Type | Notes |
|---|---|---|
| `execution_timeout_seconds` | integer, default 300 | passed to the runner in `JobAssigned`; enforced runner-side via `context.WithTimeout` |
| `max_history_records` | integer, default 1000 | (retention — see History page) |
| `anthropic_api_key` | text, nullable, `encrypted` cast | write-only from the UI's perspective |
| `anthropic_model` | string, default `claude-sonnet-5` | |
| `anthropic_effort` | string, default `high` | ignored for Haiku models, which reject the parameter |
| `runner_auto_update_enabled` | boolean, default `false` | gates whether heartbeat responses include update info at all — see [RUNNER_PROTOCOL.md](RUNNER_PROTOCOL.md#5-self-update) |

`max_concurrent_executions` existed here through v2.0.0 and was removed in v2.1.0 (see
`2026_07_07_190000_drop_max_concurrent_executions_from_app_settings_table.php`) —
concurrency is now per-runner (`runners.max_concurrent_jobs`).

### `audit_logs`

Append-only; no `updated_at` (`const UPDATED_AT = null`).

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `username` | string, nullable | defaults to the current authenticated user via `AuditLog::record()` |
| `action` | string | dot-namespaced, e.g. `Script.Executed`, `Settings.Updated` |
| `resource` | string, nullable | the thing acted on, e.g. a script name |
| `details` | text, nullable | free-form context, e.g. `"exit 0"` / `"failed (exit 1)"` |
| `created_at` | timestamp | |

### Auth & permissions

`users` (framework default, `HasUlids` not applied — auto-increment PK) plus
`spatie/laravel-permission`'s standard `roles`/`permissions`/`model_has_roles`/
`model_has_permissions`/`role_has_permissions` tables. Seeded roles and their
permissions (`database/seeders/RoleSeeder.php`):

| Role | Permissions |
|---|---|
| **Admin** | everything below, plus `settings.manage`, `users.manage`, `audit.view` |
| **Developer** | `scripts.view/run/edit/delete`, `jobs.view/manage` |
| **Operator** | `scripts.view/run`, `jobs.view` |
| **Viewer** | `scripts.view`, `jobs.view` |

`personal_access_tokens` (Sanctum's standard table) is shared between any future
first-party API tokens and runner tokens — a runner's token is distinguished by its
owning model being `Runner`, not `User`, and by the `runner` ability string passed to
`createToken('runner', ['runner'])`.

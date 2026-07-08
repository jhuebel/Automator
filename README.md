# Automator

A web application for managing and automating IaaS scripts. Built with Laravel, Livewire, and Reverb, it runs on any Linux distribution with PHP 8.3+.

## Features

- **Script Library** — store, organize, and search Bash, PowerShell, Python, Ansible, and Terraform scripts
- **Tabbed script editor** — General, Code, and Variables tabs; viewport-filling CodeMirror editor with unsaved-changes guard
- **Syntax highlighting** — per-language highlighting with read-only source viewers throughout the app
- **Script variables** — define typed variables (Text, Number, Array) per script; injected as environment variables at runtime; required-field validation blocks execution until filled
- **AI assistant** — generate, improve, and explain scripts using Claude (Anthropic API); optional, falls back gracefully when unconfigured
- **Live Script Runner** — execute scripts and stream output in real time with cancel support
- **Job Scheduler** — cron-based scheduling with a background service, live next-run preview, and per-job enable/disable
- **Execution History** — log of every run with exit codes and full output
- **Role-based access control** — Admin, Developer, Operator, and Viewer roles
- **User management** — create, edit, and disable users from the Settings page
- **Audit log** — tamper-evident record of all create/edit/delete/run actions
- **System status** — runtime dependency checks and database statistics
- **Comprehensive help system** — dedicated `/help` page, context-sensitive slide-out drawer, and inline help throughout
- **Persistent storage** — SQLite (default, zero-config) or MySQL/MariaDB

## Supported Languages

| Language | Runs via |
|---|---|
| Bash | `/bin/bash` |
| PowerShell | `pwsh` (PowerShell Core) |
| Python | `python3` |
| Ansible Playbook | `ansible-playbook` |
| Terraform | `terraform` (init + apply, in a per-run temp directory) |

## Roles

| Role | Scripts | Run Scripts | Schedule Jobs | Settings / Users |
|---|---|---|---|---|
| Admin | create / edit / delete | yes | create / edit / delete | yes |
| Developer | create / edit / delete | yes | create / edit / delete | — |
| Operator | view | yes | view | — |
| Viewer | view | — | view | — |

## AI Assistant (optional)

The script editor includes a Claude-powered AI assistant that can generate scripts from a description, improve existing scripts, and explain what a script does in plain language. It requires an [Anthropic API key](https://console.anthropic.com/settings/keys) and is completely optional.

To enable it: **Settings → AI Assistant** → paste your API key → choose a model and effort level → Save.

| Model | Best for |
|---|---|
| Haiku 4.5 | Fast iteration, low cost |
| Sonnet 5 | Balanced quality and speed (default) |
| Opus 4.8 | Complex scripts requiring deep reasoning |
| Fable 5 | Frontier reasoning for the hardest tasks (premium) |

**Effort** (Low / Medium / High / Max) controls how much the model reasons before responding, trading quality against latency and token cost. Defaults to High and is not available for Haiku, which doesn't support the parameter.

## Tech Stack

- [Laravel 13](https://laravel.com) — web framework
- [Livewire 3](https://livewire.laravel.com) + [Volt](https://livewire.laravel.com/docs/volt) — reactive server-rendered UI
- [Laravel Reverb](https://reverb.laravel.com) — WebSocket broadcasting for live script output and AI token streaming
- [Alpine.js](https://alpinejs.dev) — client-side glue for CodeMirror, charts, and the terminal/AI panels
- [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) — role-based authorization
- Eloquent ORM — SQLite (default) or MySQL/MariaDB
- [CodeMirror 6](https://codemirror.net) — syntax-highlighted editor
- [Chart.js](https://www.chartjs.org) — dashboard charts
- [dragonmantank/cron-expression](https://github.com/dragonmantank/cron-expression) — cron expression parsing
- [Anthropic API](https://docs.anthropic.com/en/api/getting-started) — Claude AI assistant (optional)

## Project Structure

```
Automator/
├── packaging/
│   ├── build.sh                    # builds the linux-x64 release archive (app + runner binaries)
│   ├── linux-common/               # shared systemd units and nginx config
│   ├── ubuntu/                     # install.sh and uninstall.sh for Ubuntu
│   ├── rhel/                       # install.sh and uninstall.sh for RHEL / Rocky / Alma
│   └── runner/                     # per-OS runner packaging (systemd unit / NSSM installer)
├── automator/                      # the Laravel application (management plane)
│   ├── app/
│   │   ├── Enums/                  # ScriptLanguage, ScriptVariableType
│   │   ├── Events/                 # Broadcast events (script output, AI tokens, job assignment)
│   │   ├── Jobs/                   # StreamClaudeCompletionJob, BroadcastDelayedEvent
│   │   ├── Http/Controllers/Api/   # RunnerController (register/heartbeat/output/finish)
│   │   ├── Livewire/Settings/      # UserManagement, RunnerManagement, SystemStatus, AuditLogViewer
│   │   ├── Models/                 # ScriptDefinition, ScheduledJob, ScriptExecutionResult, Runner, AuditLog, AppSetting, User
│   │   ├── Services/               # DependencyCheckService, RunnerAssignmentService
│   │   └── Console/Commands/       # DispatchDueJobs (scheduler tick), SweepOfflineRunners, GenerateRunnerToken
│   ├── database/migrations/
│   ├── resources/
│   │   ├── js/                     # code-editor, script-terminal, ai-assistant, chart-widget (Alpine components)
│   │   └── views/livewire/pages/   # Dashboard, Script Library, Script Editor, Runner, History, Jobs, Settings, Help
│   └── routes/
│       ├── web.php                 # page routes
│       ├── api.php                 # runner registration/heartbeat/output/finish (Sanctum)
│       ├── channels.php            # Reverb broadcast channel authorization
│       └── console.php             # scheduler + offline-sweep registration
└── runner/                         # standalone Go agent (automator-runner) — executes scripts
    ├── main.go, register.go, run.go
    ├── executor.go, pusher.go, api.go  # subprocess execution, Reverb WS client, REST client
    └── terminate_linux.go, terminate_windows.go  # OS-specific graceful cancellation
```

## Documentation

- [docs/INSTALL.md](docs/INSTALL.md) — pre-built packages, build from source, and configuration reference
- [docs/USAGE.md](docs/USAGE.md) — script editor, variables, runner, scheduler, and cron reference
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — management plane vs. runner split, request/execution flow, component map
- [docs/DATA_MODEL.md](docs/DATA_MODEL.md) — full database schema reference
- [docs/RUNNER_PROTOCOL.md](docs/RUNNER_PROTOCOL.md) — runner enrollment, REST, and WebSocket protocol reference
- [docs/SSO.md](docs/SSO.md) — Microsoft Entra ID / Google SSO setup and account-provisioning reference

## License

[MIT](LICENSE)

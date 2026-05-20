# Automator

A cross-platform web application for managing and automating IaaS scripts. Built with ASP.NET Core 9 Blazor Server, it runs on Windows Server and all major Linux distributions.

## Features

- **Script Library** — store, organize, and version Bash, PowerShell, Python, and Ansible scripts
- **Live Script Runner** — execute scripts and stream output in real time with cancel support
- **Job Scheduler** — cron-based scheduling with a background service, live next-run preview, and per-job enable/disable
- **Execution History** — searchable log of every run with exit codes and full output
- **Role-based access control** — Admin, Developer, Operator, and Viewer roles
- **User management** — create, edit, and disable users from the Settings page
- **Audit log** — tamper-evident record of all create/edit/delete/run actions
- **System status** — runtime dependency checks and database statistics
- **Persistent storage** — all scripts, jobs, and history survive restarts (SQLite)

## Supported Script Languages

| Language | Windows | Linux |
|---|---|---|
| Bash | via WSL | `/bin/bash` |
| PowerShell | `powershell.exe` | `pwsh` (PowerShell Core) |
| Python | `python.exe` | `python3` |
| Ansible Playbook | — | `ansible-playbook` |

## Roles

| Role | Scripts | Run Scripts | Schedule Jobs | Settings / Users |
|---|---|---|---|---|
| Admin | create / edit / delete | yes | create / edit / delete | yes |
| Developer | create / edit / delete | yes | create / edit / delete | — |
| Operator | view | yes | view | — |
| Viewer | view | — | view | — |

## Prerequisites

- [.NET 9 SDK](https://dot.net)
- The scripting runtimes you intend to use (`bash`, `pwsh`, `python3`, `ansible-playbook`)

## Getting Started

### Install .NET (Linux — no root required)

```bash
curl -sSL https://dot.net/v1/dotnet-install.sh | bash -s -- --channel 9.0
echo 'export DOTNET_ROOT="$HOME/.dotnet"' >> ~/.bashrc
echo 'export PATH="$HOME/.dotnet:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

### Run the app

```bash
git clone https://github.com/jhuebel/Automator.git
cd Automator
dotnet run --project src/Automator.Web
```

Open **http://localhost:5000** in your browser.

On first run the app creates `automator.db` in the project directory, seeds the four roles, and creates default users for each role (see table below). Change these credentials immediately in a production deployment.

| Username | Password | Role |
|---|---|---|
| `admin` | `Admin1234!` | Admin |
| `operator` | `Operator1234!` | Operator |
| `viewer` | `Viewer1234!` | Viewer |

Default credentials can be overridden via `appsettings.json` or environment variables before first run:

```json
"DefaultAdmin": { "Username": "myadmin", "Email": "admin@example.com", "Password": "MyStr0ng!" }
```

### Data file location

By default `automator.db` is created in the working directory. To change it:

```json
"ConnectionStrings": {
  "DefaultConnection": "Data Source=/var/lib/automator/automator.db"
}
```

## Project Structure

```
Automator/
├── src/
│   └── Automator.Web/
│       ├── Components/
│       │   ├── Layout/         # Shell layout, nav sidebar, header
│       │   ├── Pages/          # Dashboard, Script Library, Runner, History, Jobs, Settings
│       │   └── Shared/         # UserManagementPanel, SystemStatusPanel, PageHelp, HelpIcon
│       ├── Data/
│       │   ├── AutomatorDbContext.cs   # EF Core context (Scripts, ExecutionHistory, ScheduledJobs, Settings, AuditLogs)
│       │   └── DataSeeder.cs           # First-run role, user, and settings seeding
│       ├── Models/             # ScriptDefinition, ScheduledJob, ScriptExecutionResult, AuditLog, AppSetting
│       ├── Services/
│       │   ├── ScriptRunnerService         # Executes scripts as subprocesses, persists results
│       │   ├── JobSchedulerService         # Cron job store backed by SQLite
│       │   ├── SchedulerBackgroundService  # 15s tick, fires due jobs
│       │   ├── AuditLogService             # Writes audit entries to DB
│       │   └── DependencyCheckService      # Probes runtimes for System Status page
│       └── wwwroot/
└── Automator.sln
```

## Cron Schedule Reference

Jobs use standard 5-field cron syntax: `minute hour day month day-of-week`

| Expression | Schedule |
|---|---|
| `* * * * *` | Every minute |
| `*/5 * * * *` | Every 5 minutes |
| `0 * * * *` | Every hour |
| `0 8 * * *` | Daily at 8 am |
| `0 0 * * 0` | Weekly on Sunday midnight |
| `0 0 1 * *` | Monthly on the 1st |

## Tech Stack

- [ASP.NET Core 9](https://learn.microsoft.com/aspnet/core) — web framework
- [Blazor Server](https://learn.microsoft.com/aspnet/core/blazor) — interactive UI with real-time output streaming
- [ASP.NET Core Identity](https://learn.microsoft.com/aspnet/core/security/authentication/identity) — authentication and role-based authorization
- [Entity Framework Core 9 + SQLite](https://learn.microsoft.com/ef/core) — persistent storage, no external database required
- [MudBlazor 9](https://mudblazor.com) — component library
- [Chart.js 4](https://www.chartjs.org) — dashboard execution chart
- [Cronos](https://github.com/HangfireIO/Cronos) — cron expression parsing

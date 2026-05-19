# Automator

A cross-platform web application for managing and automating IaaS scripts. Built with ASP.NET Core 9 Blazor Server, it runs on Windows Server and all major Linux distributions.

## Features

- **Script Library** — store, organize, and version Bash, PowerShell, Python, and Ansible scripts
- **Live Script Runner** — execute scripts and stream output in real time with cancel support
- **Job Scheduler** — cron-based scheduling with a background service, live next-run preview, and per-job enable/disable
- **Execution History** — searchable log of every run with exit codes and full output

## Supported Script Languages

| Language | Windows | Linux |
|---|---|---|
| Bash | via WSL | `/bin/bash` |
| PowerShell | `powershell.exe` | `pwsh` (PowerShell Core) |
| Python | `python.exe` | `python3` |
| Ansible Playbook | — | `ansible-playbook` |

## Prerequisites

- [.NET 9 SDK](https://dot.net) (or .NET 8 LTS)
- The scripting runtimes you intend to use (bash, pwsh, python3, ansible-playbook)

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

## Project Structure

```
Automator/
├── src/
│   └── Automator.Web/
│       ├── Components/
│       │   ├── Layout/         # Nav and shell layout
│       │   └── Pages/          # Dashboard, Script Library, Runner, History, Jobs
│       ├── Models/             # ScriptDefinition, ScheduledJob, ExecutionResult
│       ├── Services/
│       │   ├── ScriptRunnerService      # Executes scripts as subprocesses
│       │   ├── JobSchedulerService      # In-memory cron job store (Cronos)
│       │   └── SchedulerBackgroundService  # 15s tick, fires due jobs
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
| `0 8 * * *` | Daily at 8am UTC |
| `0 0 * * 0` | Weekly on Sunday midnight |
| `0 0 1 * *` | Monthly on the 1st |

## Tech Stack

- [ASP.NET Core 9](https://learn.microsoft.com/aspnet/core) — web framework
- [Blazor Server](https://learn.microsoft.com/aspnet/core/blazor) — interactive UI with real-time output streaming
- [Cronos](https://github.com/HangfireIO/Cronos) — cron expression parsing
- [Bootstrap 5](https://getbootstrap.com) + [Bootstrap Icons](https://icons.getbootstrap.com) — UI

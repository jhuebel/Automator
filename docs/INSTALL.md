# Installation

Automator's management plane is a Laravel application (PHP, Laravel Reverb for
WebSocket broadcasting, a scheduler tick, and a web server in front of PHP-FPM). Script
execution itself is never done by the management plane — every execution runs on a
**runner**, a small Go agent that registers with the management plane and executes
scripts locally on whatever host it's installed on. Runners can run on Linux or Windows,
and you can register as many as you like (see [Runners](#runners) below). A fresh
single-box install registers one local runner automatically so a single machine still
works out of the box.

## Pre-built Packages

Download the latest release archive from the [Releases page](https://github.com/jhuebel/Automator/releases).
The archive ships a pre-built app (`vendor/` installed, frontend assets compiled) plus
pre-built `automator-runner` binaries for Linux and Windows, so the target box only needs
PHP, nginx, and Composer — not Node or Go.

### Ubuntu

```bash
mkdir automator-release && tar -xzf automator-<version>-linux-x64.tar.gz -C automator-release
cd automator-release
sudo bash packaging/ubuntu/install.sh
```

The install script:
- Installs `nginx`, PHP 8.3 (`fpm`, `cli`, `sqlite3`, `mbstring`, `xml`, `curl`, `bcmath`) via `apt-get`
- Creates `automator` and `automator-runner` system users
- Copies the app to `/opt/automator/app` and the SQLite database to `/opt/automator/data`
- Generates a production `.env` (app key, Reverb credentials, SQLite path)
- Runs migrations (and seeds default roles/users/example scripts on first install only)
- Configures a dedicated php-fpm pool
- Installs systemd units for Reverb, the scheduler timer, and the runner offline sweep
- Writes an nginx reverse proxy config to `/etc/nginx/conf.d/automator.conf`
- Installs the Linux runner binary to `/opt/automator-runner`, generates a one-time
  enrollment token, registers a local runner (tagged `linux`) against `http://127.0.0.1`,
  and starts the `automator-runner` service

### RHEL / Rocky / AlmaLinux

```bash
mkdir automator-release && tar -xzf automator-<version>-linux-x64.tar.gz -C automator-release
cd automator-release
sudo bash packaging/rhel/install.sh
```

Same as Ubuntu, but uses `dnf`, applies `setsebool -P httpd_can_network_connect 1` for
SELinux, and opens the HTTP port via `firewall-cmd` if firewalld is active.

### Installed paths

| Path | Contents |
|---|---|
| `/opt/automator/app` | Application code, `vendor/`, compiled frontend assets |
| `/opt/automator/data` | SQLite database (`database.sqlite`) |
| `/opt/automator/app/.env` | App key, database, queue, and Reverb configuration |
| `/opt/automator-runner` | Local runner binary |
| `/etc/automator-runner/config.json` | Local runner's registration (server URL, bearer token, runner id) |
| `/etc/systemd/system/automator-runner.service` | Local runner daemon |
| `/etc/systemd/system/automator-runner-sweep.{service,timer}` | Offline-runner sweep, fired every 30s |
| `/etc/systemd/system/automator-reverb.service` | Reverb WebSocket broadcasting server |
| `/etc/systemd/system/automator-scheduler.{service,timer}` | Scheduler tick, fired every minute |
| `/etc/php/8.3/fpm/pool.d/automator.conf` (Ubuntu) or `/etc/php-fpm.d/automator.conf` (RHEL) | Dedicated php-fpm pool |
| `/etc/nginx/conf.d/automator.conf` | nginx reverse proxy config (app + Reverb) |

### Uninstall

```bash
mkdir automator-release && tar -xzf automator-<version>-linux-x64.tar.gz -C automator-release
cd automator-release
sudo bash packaging/ubuntu/uninstall.sh   # or packaging/rhel/uninstall.sh
```

---

## Build from Source

### Prerequisites

- PHP 8.3+ with `sqlite3`, `mbstring`, `xml`, `curl`, `bcmath` extensions
- Composer
- Node.js 20+ / npm (for building frontend assets)
- Go (see `runner/go.mod` for the required version; for building the `automator-runner` binary)
- Scripting runtimes you intend to use — `bash`, `pwsh`, `python3`, `ansible-playbook`, `terraform` —
  installed on whichever host(s) run the `automator-runner` agent, not necessarily on the management
  plane host itself. In local dev the auto-registered runner runs on the same machine, so install them
  here too. Settings → System Status shows what each registered runner reports.

### Run the App (development)

```bash
git clone https://github.com/jhuebel/Automator.git
cd Automator/automator
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
composer run dev
```

`composer run dev` runs the app server, the queue listener (for AI assistant streaming),
Reverb, the scheduler, the runner-offline sweep, and the Vite dev server together. Open
**http://localhost:8000** in your browser, then register at least one runner (Settings →
Runners → Generate Token, then `go run ./runner register ...` from the `runner/`
directory, or a pre-built `automator-runner` binary) — the app has no local execution
path, so scripts won't run until a runner is registered and online.

### Build a Release Archive

```bash
bash packaging/build.sh
```

Produces `dist/automator-<version>-linux-x64.tar.gz`.

---

## First Run

On first install Automator seeds roles, default users, and a handful of example scripts.
**Change these credentials immediately in a production deployment.**

| Username | Password | Role |
|---|---|---|
| `admin` | `Admin1234!` | Admin |
| `operator` | `Operator1234!` | Operator |
| `viewer` | `Viewer1234!` | Viewer |

Default credentials can be overridden via environment variables before first install:

```bash
AUTOMATOR_ADMIN_USERNAME=myadmin
AUTOMATOR_ADMIN_EMAIL=admin@example.com
AUTOMATOR_ADMIN_PASSWORD='MyStr0ng!'
```

(Also available for `AUTOMATOR_OPERATOR_*` and `AUTOMATOR_VIEWER_*`.) Add these to
`/opt/automator/app/.env` before running the install script's migrate/seed step, or before
your first `php artisan db:seed` in a manual setup.

---

## Configuration

All configuration lives in `/opt/automator/app/.env`. Key settings:

### Database

SQLite is the default and requires no extra setup:

```bash
DB_CONNECTION=sqlite
DB_DATABASE=/opt/automator/data/database.sqlite
```

For MySQL/MariaDB:

```bash
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=automator
DB_USERNAME=automator
DB_PASSWORD=secret
```

Run `php artisan migrate --force` after changing the connection.

### Runners

Every script execution runs on a registered runner — there is no local execution
fallback, even on a single-box install (the install script registers one runner on the
same host automatically). Concurrency is per-runner (`max_concurrent_jobs`, set when
registering) rather than a single global setting.

Register additional runners from **Settings → Runners**:
1. Click **Generate Token** to get a one-time enrollment token (expires in 1 hour).
2. On the target host (Linux or Windows), install the `automator-runner` binary from the
   release archive's `runner/linux/` or `runner/windows/` directory, then run:
   ```bash
   automator-runner register --server https://your-automator-host \
     --token <enrollment-token> --name my-runner --tags linux,terraform
   automator-runner run   # or install as a service — see packaging/runner/
   ```
3. The new runner appears in Settings → Runners once it connects. Assign
   `required_runner_tags` on a scheduled job (or on an ad-hoc run) to route work to
   runners with matching tags; otherwise any online runner with spare capacity is
   eligible, chosen least-busy-first.

A runner is marked offline (and any in-flight execution failed) if it misses 3
consecutive heartbeats (~45s by default). See `packaging/runner/linux/` (systemd) and
`packaging/runner/windows/` (NSSM-based) for per-runner service installers.

### Reverb (live output streaming)

Reverb powers the live script-output terminal and the AI assistant's streaming responses.
It listens on `127.0.0.1:8080` by default and is reverse-proxied through nginx at `/app/`.
The install script generates `REVERB_APP_KEY`/`REVERB_APP_SECRET` automatically. If you
change them, restart both `automator-reverb` and php-fpm.

### AI Assistant

Configure the Anthropic API key, model, and effort level from **Settings → AI Assistant**
in the app (stored encrypted in the database) — no `.env` changes needed.

---

## Manual Service Installation

Use this section if you built from source and want to deploy without the pre-built
packaging scripts. See `packaging/linux-common/` for the exact unit files and nginx config
referenced below.

1. Install PHP 8.3+, Composer, nginx (the script runtimes — `bash`, `pwsh`, `python3`,
   `ansible-playbook`, `terraform` — only need to be present on runner hosts, not here).
2. Copy the app to `/opt/automator/app`, run `composer install --no-dev --optimize-autoloader`.
3. Configure `.env` (database, `QUEUE_CONNECTION=database`, `BROADCAST_CONNECTION=reverb`, Reverb credentials).
4. `php artisan key:generate --force && php artisan migrate --force && php artisan db:seed --force`
5. Configure a php-fpm pool running as a dedicated `automator` user.
6. Install and enable the systemd units from `packaging/linux-common/`:
   - `automator-reverb.service`
   - `automator-scheduler.timer` (+ its paired `.service`)
   - `automator-runner-sweep.timer` (+ its paired `.service`) — flips stale runners offline
7. Install `packaging/linux-common/nginx-automator.conf` to your nginx `conf.d/`, adjusting
   the php-fpm socket path if needed, then `nginx -t && systemctl restart nginx`.
8. Register at least one runner — see [Runners](#runners) above. `packaging/runner/linux/`
   and `packaging/runner/windows/` have per-OS binary + service installers.

On RHEL / Rocky / AlmaLinux, also run:

```bash
setsebool -P httpd_can_network_connect 1      # SELinux
firewall-cmd --permanent --add-service=http   # firewalld
firewall-cmd --reload
```

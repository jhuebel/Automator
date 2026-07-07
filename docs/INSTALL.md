# Installation

Automator is a Laravel application. It requires PHP, a queue worker, Laravel Reverb
(WebSocket broadcasting), a scheduler tick, and a web server in front of PHP-FPM.

## Pre-built Packages

Download the latest release archive from the [Releases page](https://github.com/jhuebel/Automator/releases).
The archive ships a pre-built app (`vendor/` installed, frontend assets compiled), so the
target box only needs PHP, nginx, and Composer — not Node.

### Ubuntu

```bash
mkdir automator && tar -xzf automator-<version>-linux-x64.tar.gz -C automator
cd automator
sudo bash packaging/ubuntu/install.sh
```

The install script:
- Installs `nginx`, PHP 8.3 (`fpm`, `cli`, `sqlite3`, `mbstring`, `xml`, `curl`, `bcmath`) via `apt-get`
- Creates an `automator` system user
- Copies the app to `/opt/automator/app` and the SQLite database to `/opt/automator/data`
- Generates a production `.env` (app key, Reverb credentials, SQLite path)
- Runs migrations (and seeds default roles/users/example scripts on first install only)
- Configures a dedicated php-fpm pool
- Installs systemd units for the queue workers, Reverb, and the scheduler timer
- Writes an nginx reverse proxy config to `/etc/nginx/conf.d/automator.conf`

### RHEL / Rocky / AlmaLinux

```bash
mkdir automator && tar -xzf automator-<version>-linux-x64.tar.gz -C automator
cd automator
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
| `/etc/systemd/system/automator-worker@.service` | Queue worker template (instances `1`–`5`) |
| `/etc/systemd/system/automator-reverb.service` | Reverb WebSocket broadcasting server |
| `/etc/systemd/system/automator-scheduler.{service,timer}` | Scheduler tick, fired every minute |
| `/etc/php/8.3/fpm/pool.d/automator.conf` (Ubuntu) or `/etc/php-fpm.d/automator.conf` (RHEL) | Dedicated php-fpm pool |
| `/etc/nginx/conf.d/automator.conf` | nginx reverse proxy config (app + Reverb) |

### Uninstall

```bash
mkdir automator && tar -xzf automator-<version>-linux-x64.tar.gz -C automator
cd automator
sudo bash packaging/ubuntu/uninstall.sh   # or packaging/rhel/uninstall.sh
```

---

## Build from Source

### Prerequisites

- PHP 8.3+ with `sqlite3`, `mbstring`, `xml`, `curl`, `bcmath` extensions
- Composer
- Node.js 20+ / npm (for building frontend assets)
- Scripting runtimes you intend to use: `bash`, `pwsh`, `python3`, `ansible-playbook`, `terraform`

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

`composer run dev` runs the app server, queue worker, Reverb, the scheduler, and the Vite
dev server together. Open **http://localhost:8000** in your browser.

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

### Queue / Execution Concurrency

Script executions run as queued jobs on the `executions` queue. The number of running
`automator-worker@N` systemd instances is the concurrency ceiling — matching the
"Max Concurrent Executions" value in Settings requires enabling/disabling worker instances:

```bash
sudo systemctl enable --now automator-worker@6   # raise from 5 to 6 workers
sudo systemctl disable --now automator-worker@6  # lower back down
```

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

1. Install PHP 8.3+, Composer, nginx, and the script runtimes you need.
2. Copy the app to `/opt/automator/app`, run `composer install --no-dev --optimize-autoloader`.
3. Configure `.env` (database, `QUEUE_CONNECTION=database`, `BROADCAST_CONNECTION=reverb`, Reverb credentials).
4. `php artisan key:generate --force && php artisan migrate --force && php artisan db:seed --force`
5. Configure a php-fpm pool running as a dedicated `automator` user.
6. Install and enable the systemd units from `packaging/linux-common/`:
   - `automator-worker@.service` (enable N instances for your desired concurrency)
   - `automator-reverb.service`
   - `automator-scheduler.timer` (+ its paired `.service`)
7. Install `packaging/linux-common/nginx-automator.conf` to your nginx `conf.d/`, adjusting
   the php-fpm socket path if needed, then `nginx -t && systemctl restart nginx`.

On RHEL / Rocky / AlmaLinux, also run:

```bash
setsebool -P httpd_can_network_connect 1      # SELinux
firewall-cmd --permanent --add-service=http   # firewalld
firewall-cmd --reload
```

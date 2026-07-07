#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Automator — RHEL / Rocky / AlmaLinux install script
# Run as root from within the extracted archive directory:
#
#   mkdir automator-release && tar -xzf automator-<version>-linux-x64.tar.gz -C automator-release
#   cd automator-release
#   sudo bash packaging/rhel/install.sh
#
# Installs the management plane (nginx + PHP-FPM + Reverb + scheduler) AND
# registers a local Go runner on this same host, since there is no local
# execution path anymore — every script execution goes through a registered
# runner, even on a single-box install.
# ---------------------------------------------------------------------------

APP_USER="automator"
APP_DIR="/opt/automator/app"
DATA_DIR="/opt/automator/data"
PHP_FPM_POOL="/etc/php-fpm.d/automator.conf"
PHP_FPM_SOCK="/run/php-fpm/automator.sock"
NGINX_CONF="/etc/nginx/conf.d/automator.conf"

RUNNER_USER="automator-runner"
RUNNER_BIN_DIR="/opt/automator-runner"
RUNNER_CONF_DIR="/etc/automator-runner"
RUNNER_NAME="local-$(hostname)"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ARCHIVE_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
APP_ROOT="$ARCHIVE_ROOT/automator"

# --- helpers ----------------------------------------------------------------
info()  { echo "[INFO]  $*"; }
warn()  { echo "[WARN]  $*"; }
die()   { echo "[ERROR] $*" >&2; exit 1; }

# --- checks -----------------------------------------------------------------
[[ $EUID -eq 0 ]] || die "This script must be run as root (use sudo)."
[[ -f "$APP_ROOT/artisan" ]] || die "artisan not found in $APP_ROOT. Extract the archive first and run this script from within it."
[[ -f "$ARCHIVE_ROOT/runner/linux/automator-runner" ]] || die "runner binary not found in $ARCHIVE_ROOT/runner/linux. Extract the archive first and run this script from within it."

FIRST_INSTALL=1
[[ -f "$DATA_DIR/database.sqlite" ]] && FIRST_INSTALL=0

# --- install dependencies ---------------------------------------------------
info "Installing dependencies..."
dnf install -y nginx curl \
    php php-fpm php-cli php-pdo php-mbstring php-xml php-bcmath php-process composer

# --- create system user -----------------------------------------------------
if ! id "$APP_USER" &>/dev/null; then
    info "Creating system user '$APP_USER'..."
    useradd --system --no-create-home --shell /sbin/nologin "$APP_USER"
fi

# --- create directories -----------------------------------------------------
info "Creating application directories..."
mkdir -p "$APP_DIR" "$DATA_DIR"

# --- copy application files -------------------------------------------------
info "Copying application files..."
find "$APP_ROOT" -maxdepth 1 -mindepth 1 -exec cp -r {} "$APP_DIR/" \;
mkdir -p "$APP_DIR/storage/framework/"{cache,sessions,views} "$APP_DIR/storage/logs"

# --- write production .env ---------------------------------------------------
if [[ ! -f "$APP_DIR/.env" ]]; then
    info "Writing production .env..."
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
    REVERB_KEY=$(php -r "echo bin2hex(random_bytes(10));")
    REVERB_SECRET=$(php -r "echo bin2hex(random_bytes(20));")
    {
        echo "APP_ENV=production"
        echo "APP_DEBUG=false"
        echo "DB_CONNECTION=sqlite"
        echo "DB_DATABASE=$DATA_DIR/database.sqlite"
        echo "QUEUE_CONNECTION=database"
        echo "BROADCAST_CONNECTION=reverb"
        echo "REVERB_APP_ID=automator"
        echo "REVERB_APP_KEY=$REVERB_KEY"
        echo "REVERB_APP_SECRET=$REVERB_SECRET"
        echo "REVERB_HOST=localhost"
        echo "REVERB_PORT=8080"
        echo "REVERB_SCHEME=http"
        echo "REVERB_SERVER_HOST=127.0.0.1"
        echo "REVERB_SERVER_PORT=8080"
        echo "VITE_REVERB_APP_KEY=\"\${REVERB_APP_KEY}\""
        echo "VITE_REVERB_HOST=\"\${REVERB_HOST}\""
        echo "VITE_REVERB_PORT=\"\${REVERB_PORT}\""
        echo "VITE_REVERB_SCHEME=\"\${REVERB_SCHEME}\""
    } >> "$APP_DIR/.env"
fi

touch "$DATA_DIR/database.sqlite"

# --- install PHP dependencies, run migrations, cache config ------------------
info "Running composer install (if vendor/ is missing) and migrations..."
cd "$APP_DIR"
[[ -d vendor ]] || sudo -u "$APP_USER" composer install --no-dev --optimize-autoloader --no-interaction
sudo -u "$APP_USER" php artisan key:generate --force
sudo -u "$APP_USER" php artisan migrate --force

if [[ "$FIRST_INSTALL" -eq 1 ]]; then
    info "Seeding default roles, users, and example scripts..."
    sudo -u "$APP_USER" php artisan db:seed --force
fi

sudo -u "$APP_USER" php artisan config:cache
sudo -u "$APP_USER" php artisan route:cache
sudo -u "$APP_USER" php artisan view:cache

# --- set ownership ----------------------------------------------------------
chown -R "$APP_USER:$APP_USER" /opt/automator

# --- configure php-fpm pool ---------------------------------------------------
info "Configuring php-fpm pool..."
cat > "$PHP_FPM_POOL" <<EOF
[automator]
user = $APP_USER
group = $APP_USER
listen = $PHP_FPM_SOCK
listen.owner = nginx
listen.group = nginx
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 4
EOF
systemctl enable --now php-fpm
systemctl restart php-fpm

# --- install systemd units (management plane) --------------------------------
info "Installing systemd units..."
cp "$ARCHIVE_ROOT/packaging/linux-common/automator-reverb.service"        /etc/systemd/system/
cp "$ARCHIVE_ROOT/packaging/linux-common/automator-scheduler.service"     /etc/systemd/system/
cp "$ARCHIVE_ROOT/packaging/linux-common/automator-scheduler.timer"       /etc/systemd/system/
cp "$ARCHIVE_ROOT/packaging/linux-common/automator-runner-sweep.service"  /etc/systemd/system/
cp "$ARCHIVE_ROOT/packaging/linux-common/automator-runner-sweep.timer"    /etc/systemd/system/
systemctl daemon-reload

systemctl enable --now automator-reverb
systemctl enable --now automator-scheduler.timer
systemctl enable --now automator-runner-sweep.timer

# --- configure nginx ---------------------------------------------------------
info "Configuring nginx..."
cp "$ARCHIVE_ROOT/packaging/linux-common/nginx-automator.conf" "$NGINX_CONF"
sed -i "s#unix:/run/php/automator.sock#unix:$PHP_FPM_SOCK#" "$NGINX_CONF"
nginx -t
systemctl enable --now nginx
systemctl restart nginx

# --- SELinux: allow nginx to proxy to localhost and reach php-fpm socket -----
if command -v setsebool &>/dev/null && sestatus &>/dev/null 2>&1; then
    info "Configuring SELinux: allowing nginx network connections..."
    setsebool -P httpd_can_network_connect 1
fi

# --- firewalld: open HTTP ---------------------------------------------------
if systemctl is-active --quiet firewalld 2>/dev/null; then
    info "Opening HTTP port in firewalld..."
    firewall-cmd --permanent --add-service=http
    firewall-cmd --reload
fi

# --- install and register a local runner on this same host -------------------
if [[ ! -f "$RUNNER_CONF_DIR/config.json" ]]; then
    info "Installing local runner..."
    if ! id "$RUNNER_USER" &>/dev/null; then
        useradd --system --no-create-home --shell /sbin/nologin "$RUNNER_USER"
    fi

    mkdir -p "$RUNNER_BIN_DIR" "$RUNNER_CONF_DIR"
    cp "$ARCHIVE_ROOT/runner/linux/automator-runner" "$RUNNER_BIN_DIR/automator-runner"
    chmod +x "$RUNNER_BIN_DIR/automator-runner"
    cp "$ARCHIVE_ROOT/runner/linux/automator-runner.service" /etc/systemd/system/
    chown -R "$RUNNER_USER:$RUNNER_USER" "$RUNNER_BIN_DIR" "$RUNNER_CONF_DIR"
    systemctl daemon-reload

    info "Generating a one-time enrollment token and registering the local runner..."
    ENROLLMENT_TOKEN=$(sudo -u "$APP_USER" php "$APP_DIR/artisan" automator:generate-runner-token --ttl=5 | tail -n1)

    sudo -u "$RUNNER_USER" "$RUNNER_BIN_DIR/automator-runner" register \
        --server "http://127.0.0.1" \
        --token "$ENROLLMENT_TOKEN" \
        --name "$RUNNER_NAME" \
        --tags linux \
        --config "$RUNNER_CONF_DIR/config.json"

    chown "$RUNNER_USER:$RUNNER_USER" "$RUNNER_CONF_DIR/config.json"
    chmod 600 "$RUNNER_CONF_DIR/config.json"

    systemctl enable --now automator-runner
else
    info "Local runner already registered, skipping enrollment."
fi

# --- done -------------------------------------------------------------------
info ""
info "Automator installed successfully!"
info "  App:       http://$(hostname -I | awk '{print $1}')"
info "  Runner:    systemctl status automator-runner"
info "  Reverb:    journalctl -u automator-reverb -f"
info "  Scheduler: journalctl -u automator-scheduler -f"
info "  Data:      $DATA_DIR"
info ""
info "Register additional runners (on this host or others) from Settings > Runners."
warn "Default credentials are admin/Admin1234! — change them immediately."

#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Automator — Ubuntu install script
# Run as root from within the extracted archive directory:
#
#   mkdir automator && tar -xzf automator-<version>-linux-x64.tar.gz -C automator
#   cd automator
#   sudo bash packaging/ubuntu/install.sh
# ---------------------------------------------------------------------------

APP_USER="automator"
APP_DIR="/opt/automator/app"
DATA_DIR="/opt/automator/data"
PHP_VERSION="8.3"
PHP_FPM_POOL="/etc/php/${PHP_VERSION}/fpm/pool.d/automator.conf"
PHP_FPM_SOCK="/run/php/automator.sock"
NGINX_CONF="/etc/nginx/conf.d/automator.conf"
WORKER_COUNT=5

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

# --- helpers ----------------------------------------------------------------
info()  { echo "[INFO]  $*"; }
warn()  { echo "[WARN]  $*"; }
die()   { echo "[ERROR] $*" >&2; exit 1; }

# --- checks -----------------------------------------------------------------
[[ $EUID -eq 0 ]] || die "This script must be run as root (use sudo)."
[[ -f "$APP_ROOT/artisan" ]] || die "artisan not found in $APP_ROOT. Extract the archive first and run this script from within it."

FIRST_INSTALL=1
[[ -f "$DATA_DIR/database.sqlite" ]] && FIRST_INSTALL=0

# --- install dependencies ---------------------------------------------------
info "Installing dependencies..."
apt-get update -qq
apt-get install -y nginx curl \
    "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-sqlite3" \
    "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-bcmath"

# --- create system user -----------------------------------------------------
if ! id "$APP_USER" &>/dev/null; then
    info "Creating system user '$APP_USER'..."
    useradd --system --no-create-home --shell /usr/sbin/nologin "$APP_USER"
fi

# --- create directories -----------------------------------------------------
info "Creating application directories..."
mkdir -p "$APP_DIR" "$DATA_DIR"

# --- copy application files -------------------------------------------------
info "Copying application files..."
find "$APP_ROOT" -maxdepth 1 -mindepth 1 ! -name "packaging" -exec cp -r {} "$APP_DIR/" \;
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
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 4
EOF
systemctl restart "php${PHP_VERSION}-fpm"

# --- install systemd units ---------------------------------------------------
info "Installing systemd units..."
cp "$APP_ROOT/packaging/linux-common/automator-worker@.service"   /etc/systemd/system/
cp "$APP_ROOT/packaging/linux-common/automator-reverb.service"     /etc/systemd/system/
cp "$APP_ROOT/packaging/linux-common/automator-scheduler.service"  /etc/systemd/system/
cp "$APP_ROOT/packaging/linux-common/automator-scheduler.timer"    /etc/systemd/system/
systemctl daemon-reload

systemctl enable --now automator-reverb
systemctl enable --now automator-scheduler.timer
for i in $(seq 1 "$WORKER_COUNT"); do
    systemctl enable --now "automator-worker@$i"
done

# --- configure nginx ---------------------------------------------------------
info "Configuring nginx..."
cp "$APP_ROOT/packaging/linux-common/nginx-automator.conf" "$NGINX_CONF"
nginx -t
systemctl restart nginx

# --- done -------------------------------------------------------------------
info ""
info "Automator installed successfully!"
info "  App:      http://$(hostname -I | awk '{print $1}')"
info "  Workers:  systemctl status 'automator-worker@*'"
info "  Reverb:   journalctl -u automator-reverb -f"
info "  Scheduler: journalctl -u automator-scheduler -f"
info "  Data:     $DATA_DIR"
info ""
warn "Default credentials are admin/Admin1234! — change them immediately."

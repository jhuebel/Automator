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
CONF_DIR="/etc/automator"
SERVICE_FILE="/etc/systemd/system/automator.service"
NGINX_CONF="/etc/nginx/conf.d/automator.conf"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

# --- helpers ----------------------------------------------------------------
info()  { echo "[INFO]  $*"; }
warn()  { echo "[WARN]  $*"; }
die()   { echo "[ERROR] $*" >&2; exit 1; }

# --- checks -----------------------------------------------------------------
[[ $EUID -eq 0 ]] || die "This script must be run as root (use sudo)."
[[ -f "$APP_ROOT/Automator.Web" ]] || die "Automator.Web not found in $APP_ROOT. Extract the archive first and run this script from within it."

# --- install dependencies ---------------------------------------------------
info "Installing dependencies..."
apt-get update -qq
apt-get install -y nginx curl

# --- create system user -----------------------------------------------------
if ! id "$APP_USER" &>/dev/null; then
    info "Creating system user '$APP_USER'..."
    useradd --system --no-create-home --shell /usr/sbin/nologin "$APP_USER"
fi

# --- create directories -----------------------------------------------------
info "Creating application directories..."
mkdir -p "$APP_DIR" "$DATA_DIR" "$CONF_DIR"

# --- copy application files -------------------------------------------------
info "Copying application files..."
# Copy everything except the packaging/ subdirectory
find "$APP_ROOT" -maxdepth 1 -mindepth 1 ! -name "packaging" -exec cp -r {} "$APP_DIR/" \;
chmod +x "$APP_DIR/Automator.Web"

# --- write production config ------------------------------------------------
info "Writing appsettings.Production.json..."
cat > "$APP_DIR/appsettings.Production.json" <<EOF
{
  "ConnectionStrings": {
    "DefaultConnection": "Data Source=$DATA_DIR/automator.db"
  }
}
EOF

# --- set ownership ----------------------------------------------------------
chown -R "$APP_USER:$APP_USER" /opt/automator

# --- install systemd service -------------------------------------------------
info "Installing systemd service..."
cp "$APP_ROOT/automator.service" "$SERVICE_FILE"
systemctl daemon-reload
systemctl enable automator
systemctl start automator

# --- configure nginx ---------------------------------------------------------
info "Configuring nginx..."
cp "$APP_ROOT/nginx-automator.conf" "$NGINX_CONF"
nginx -t
systemctl restart nginx

# --- done -------------------------------------------------------------------
info ""
info "Automator installed successfully!"
info "  App:    http://$(hostname -I | awk '{print $1}')"
info "  Logs:   journalctl -u automator -f"
info "  Data:   $DATA_DIR"
info ""
warn "Default credentials are admin/Admin1234! — change them immediately."

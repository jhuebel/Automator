#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Automator — Ubuntu install script
# Run as root from the directory containing the linux-x64 archive.
# ---------------------------------------------------------------------------

APP_NAME="automator"
APP_USER="automator"
APP_DIR="/opt/automator/app"
DATA_DIR="/opt/automator/data"
CONF_DIR="/etc/automator"
SERVICE_FILE="/etc/systemd/system/automator.service"
NGINX_CONF="/etc/nginx/conf.d/automator.conf"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# --- helpers ----------------------------------------------------------------
info()  { echo "[INFO]  $*"; }
warn()  { echo "[WARN]  $*"; }
die()   { echo "[ERROR] $*" >&2; exit 1; }

# --- checks -----------------------------------------------------------------
[[ $EUID -eq 0 ]] || die "This script must be run as root (use sudo)."

# Locate the archive — accept an explicit path or find one in SCRIPT_DIR
ARCHIVE="${1:-}"
if [[ -z "$ARCHIVE" ]]; then
    ARCHIVE=$(find "$SCRIPT_DIR" -maxdepth 1 -name "automator-*-linux-x64.tar.gz" | sort -V | tail -1)
fi
[[ -f "$ARCHIVE" ]] || die "Archive not found. Pass the path as the first argument or place the .tar.gz in the same directory as this script."

# --- install dependencies ---------------------------------------------------
info "Installing dependencies..."
apt-get update -qq
apt-get install -y nginx curl unzip

# --- create system user -----------------------------------------------------
if ! id "$APP_USER" &>/dev/null; then
    info "Creating system user '$APP_USER'..."
    useradd --system --no-create-home --shell /usr/sbin/nologin "$APP_USER"
fi

# --- create directories -----------------------------------------------------
info "Creating application directories..."
mkdir -p "$APP_DIR" "$DATA_DIR" "$CONF_DIR"

# --- extract archive --------------------------------------------------------
info "Extracting application archive..."
tar -xzf "$ARCHIVE" -C "$APP_DIR"

# Make the binary executable
chmod +x "$APP_DIR/Automator.Web" 2>/dev/null || true

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
cp "$SCRIPT_DIR/automator.service" "$SERVICE_FILE"
systemctl daemon-reload
systemctl enable automator
systemctl start automator

# --- configure nginx ---------------------------------------------------------
info "Configuring nginx..."
cp "$SCRIPT_DIR/nginx-automator.conf" "$NGINX_CONF"

# Remove default site if it conflicts on port 80
if nginx -t -q 2>/dev/null; then
    : # config already valid
else
    warn "nginx config test failed — check $NGINX_CONF and your existing nginx sites."
fi

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

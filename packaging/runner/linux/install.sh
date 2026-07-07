#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Automator Runner — Linux install script
# Run as root from the directory containing the `automator-runner` binary:
#
#   sudo bash install.sh
#
# Registration is a separate, interactive step — run it yourself afterward:
#
#   sudo -u automator-runner /opt/automator-runner/automator-runner register \
#     --server https://automator.example.com --token <enrollment-token> \
#     --name my-runner --tags linux
# ---------------------------------------------------------------------------

BIN_DIR="/opt/automator-runner"
CONF_DIR="/etc/automator-runner"
SERVICE_FILE="/etc/systemd/system/automator-runner.service"
RUNNER_USER="automator-runner"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

info() { echo "[INFO]  $*"; }
die()  { echo "[ERROR] $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "This script must be run as root (use sudo)."
[[ -f "$SCRIPT_DIR/automator-runner" ]] || die "automator-runner binary not found next to this script."

if ! id "$RUNNER_USER" &>/dev/null; then
    info "Creating system user '$RUNNER_USER'..."
    useradd --system --no-create-home --shell /usr/sbin/nologin "$RUNNER_USER"
fi

info "Installing binary to $BIN_DIR..."
mkdir -p "$BIN_DIR" "$CONF_DIR"
cp "$SCRIPT_DIR/automator-runner" "$BIN_DIR/automator-runner"
chmod +x "$BIN_DIR/automator-runner"
chown -R "$RUNNER_USER:$RUNNER_USER" "$BIN_DIR" "$CONF_DIR"

info "Installing systemd service..."
cp "$SCRIPT_DIR/automator-runner.service" "$SERVICE_FILE"
systemctl daemon-reload

info ""
info "Installed. Register this runner, then start the service:"
info ""
info "  sudo -u $RUNNER_USER $BIN_DIR/automator-runner register \\"
info "    --server https://your-automator-host \\"
info "    --token <enrollment-token-from-Settings> \\"
info "    --name $(hostname) --tags linux \\"
info "    --config $CONF_DIR/config.json"
info ""
info "  sudo systemctl enable --now automator-runner"
info ""
info "Note: the service runs with ProtectSystem=full and a private /tmp. If a"
info "tool you run (terraform, ansible, etc.) needs to write state outside of"
info "/tmp, add its directory to ReadWritePaths= in $SERVICE_FILE."

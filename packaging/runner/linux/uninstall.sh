#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Automator Runner — Linux uninstall script. Run as root.
# ---------------------------------------------------------------------------

BIN_DIR="/opt/automator-runner"
CONF_DIR="/etc/automator-runner"
SERVICE_FILE="/etc/systemd/system/automator-runner.service"
RUNNER_USER="automator-runner"

info() { echo "[INFO]  $*"; }
die()  { echo "[ERROR] $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "This script must be run as root (use sudo)."

if [[ -f "$CONF_DIR/config.json" ]]; then
    info "Unregistering from the management plane..."
    sudo -u "$RUNNER_USER" "$BIN_DIR/automator-runner" unregister --config "$CONF_DIR/config.json" || \
        info "Unregister call failed (server unreachable?) — remove the runner manually from Settings > Runners."
fi

info "Stopping and disabling service..."
systemctl disable --now automator-runner 2>/dev/null || true
rm -f "$SERVICE_FILE"
systemctl daemon-reload

info "Removing files..."
rm -rf "$BIN_DIR" "$CONF_DIR"

read -r -p "Remove the '$RUNNER_USER' system user? [y/N] " REPLY
if [[ "${REPLY,,}" == "y" ]]; then
    id "$RUNNER_USER" &>/dev/null && userdel "$RUNNER_USER"
    info "User '$RUNNER_USER' removed."
fi

info "Automator Runner has been uninstalled."

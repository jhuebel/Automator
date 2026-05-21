#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Automator — RHEL / Rocky / AlmaLinux uninstall script
# Run as root.
# ---------------------------------------------------------------------------

SERVICE_FILE="/etc/systemd/system/automator.service"
NGINX_CONF="/etc/nginx/conf.d/automator.conf"

info() { echo "[INFO]  $*"; }
die()  { echo "[ERROR] $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "This script must be run as root (use sudo)."

# --- stop and remove service ------------------------------------------------
if systemctl is-active --quiet automator 2>/dev/null; then
    info "Stopping automator service..."
    systemctl stop automator
fi
if systemctl is-enabled --quiet automator 2>/dev/null; then
    info "Disabling automator service..."
    systemctl disable automator
fi
if [[ -f "$SERVICE_FILE" ]]; then
    info "Removing service file..."
    rm -f "$SERVICE_FILE"
    systemctl daemon-reload
fi

# --- remove nginx config ----------------------------------------------------
if [[ -f "$NGINX_CONF" ]]; then
    info "Removing nginx config..."
    rm -f "$NGINX_CONF"
    systemctl restart nginx
fi

# --- remove application files -----------------------------------------------
info "Removing application files..."
rm -rf /opt/automator /etc/automator

# --- optionally remove system user ------------------------------------------
read -r -p "Remove the 'automator' system user? [y/N] " REPLY
if [[ "${REPLY,,}" == "y" ]]; then
    if id automator &>/dev/null; then
        userdel automator
        info "User 'automator' removed."
    fi
fi

info "Automator has been uninstalled."

#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Automator — Ubuntu uninstall script
# Run as root.
# ---------------------------------------------------------------------------

PHP_VERSION="8.3"
PHP_FPM_POOL="/etc/php/${PHP_VERSION}/fpm/pool.d/automator.conf"
NGINX_CONF="/etc/nginx/conf.d/automator.conf"
WORKER_COUNT=5

info() { echo "[INFO]  $*"; }
die()  { echo "[ERROR] $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "This script must be run as root (use sudo)."

# --- stop and remove systemd units -------------------------------------------
info "Stopping and disabling services..."
for i in $(seq 1 "$WORKER_COUNT"); do
    systemctl disable --now "automator-worker@$i" 2>/dev/null || true
done
systemctl disable --now automator-reverb 2>/dev/null || true
systemctl disable --now automator-scheduler.timer 2>/dev/null || true

rm -f /etc/systemd/system/automator-worker@.service
rm -f /etc/systemd/system/automator-reverb.service
rm -f /etc/systemd/system/automator-scheduler.service
rm -f /etc/systemd/system/automator-scheduler.timer
systemctl daemon-reload

# --- remove php-fpm pool -----------------------------------------------------
if [[ -f "$PHP_FPM_POOL" ]]; then
    info "Removing php-fpm pool config..."
    rm -f "$PHP_FPM_POOL"
    systemctl restart "php${PHP_VERSION}-fpm" 2>/dev/null || true
fi

# --- remove nginx config ----------------------------------------------------
if [[ -f "$NGINX_CONF" ]]; then
    info "Removing nginx config..."
    rm -f "$NGINX_CONF"
    systemctl restart nginx 2>/dev/null || true
fi

# --- remove application files -----------------------------------------------
info "Removing application files..."
rm -rf /opt/automator

# --- optionally remove system user ------------------------------------------
read -r -p "Remove the 'automator' system user? [y/N] " REPLY
if [[ "${REPLY,,}" == "y" ]]; then
    if id automator &>/dev/null; then
        userdel automator
        info "User 'automator' removed."
    fi
fi

info "Automator has been uninstalled."

#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Automator — build release archive
# Run from the repository root.
# Produces:
#   dist/automator-<version>-linux-x64.tar.gz
#
# The archive ships a pre-built Laravel app (vendor/ installed, frontend
# assets compiled) so the target box only needs PHP + nginx, not Node/Composer.
# ---------------------------------------------------------------------------

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

APP_DIR="automator"
VERSION=$(grep '"version"' "$APP_DIR/composer.json" 2>/dev/null | sed -E 's/.*"version":\s*"([^"]+)".*/\1/' | tr -d '[:space:]')
VERSION="${VERSION:-0.0.0}"
OUTDIR="dist"
BUILD_DIR="build/linux-x64"

info() { echo "[INFO]  $*"; }

info "Building Automator v${VERSION}..."
rm -rf "$BUILD_DIR"
mkdir -p "$OUTDIR" "$BUILD_DIR"

# ---------------------------------------------------------------------------
# Install PHP + JS dependencies and compile frontend assets
# ---------------------------------------------------------------------------
pushd "$APP_DIR" >/dev/null

info "Installing composer dependencies (production)..."
composer install --no-dev --optimize-autoloader --no-interaction --quiet

info "Installing npm dependencies and building assets..."
npm ci --no-audit --no-fund
npm run build

popd >/dev/null

# ---------------------------------------------------------------------------
# Copy application files, excluding dev-only artifacts
# ---------------------------------------------------------------------------
info "Copying application files..."
rsync -a \
    --exclude 'node_modules' \
    --exclude '.git' \
    --exclude 'tests' \
    --exclude '.env' \
    --exclude 'database/database.sqlite' \
    --exclude 'storage/logs/*' \
    --exclude 'storage/framework/cache/data/*' \
    --exclude 'storage/framework/sessions/*' \
    --exclude 'storage/framework/views/*' \
    "$APP_DIR/" "$BUILD_DIR/"

# Bundle shared systemd + nginx configs
mkdir -p "$BUILD_DIR/packaging/linux-common"
cp packaging/linux-common/automator-worker@.service   "$BUILD_DIR/packaging/linux-common/"
cp packaging/linux-common/automator-reverb.service     "$BUILD_DIR/packaging/linux-common/"
cp packaging/linux-common/automator-scheduler.service   "$BUILD_DIR/packaging/linux-common/"
cp packaging/linux-common/automator-scheduler.timer     "$BUILD_DIR/packaging/linux-common/"
cp packaging/linux-common/nginx-automator.conf          "$BUILD_DIR/packaging/linux-common/"

# Bundle distro-specific install/uninstall scripts
mkdir -p "$BUILD_DIR/packaging/ubuntu" "$BUILD_DIR/packaging/rhel"
cp packaging/ubuntu/install.sh    "$BUILD_DIR/packaging/ubuntu/"
cp packaging/ubuntu/uninstall.sh  "$BUILD_DIR/packaging/ubuntu/"
cp packaging/rhel/install.sh      "$BUILD_DIR/packaging/rhel/"
cp packaging/rhel/uninstall.sh    "$BUILD_DIR/packaging/rhel/"

ARCHIVE="$OUTDIR/automator-${VERSION}-linux-x64.tar.gz"
info "Creating $ARCHIVE..."
tar -czf "$ARCHIVE" -C "$BUILD_DIR" .
info "  $(du -sh "$ARCHIVE" | cut -f1)  $ARCHIVE"

rm -rf "build"

info ""
info "Done. Package: $ARCHIVE"

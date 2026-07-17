#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Automator — build release archive
# Run from the repository root.
# Produces:
#   dist/automator-<version>-linux-x64.tar.gz
#
# The archive ships a pre-built Laravel app (vendor/ installed, frontend
# assets compiled) plus a pre-built automator-runner binary (Linux and
# Windows), so the target box only needs PHP + nginx — not Node, Composer,
# or Go.
# ---------------------------------------------------------------------------

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

APP_DIR="automator"
RUNNER_DIR="runner"
VERSION=$(grep "'version'" "$APP_DIR/config/automator.php" 2>/dev/null | sed -E "s/.*'version'.*=>\s*'([^']+)'.*/\1/" | tr -d '[:space:]') || true
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
# Cross-compile the runner binary for Linux and Windows
# ---------------------------------------------------------------------------
pushd "$RUNNER_DIR" >/dev/null

info "Building automator-runner (linux/amd64)..."
GOOS=linux GOARCH=amd64 go build -o automator-runner-linux-amd64 .

info "Building automator-runner (windows/amd64)..."
GOOS=windows GOARCH=amd64 go build -o automator-runner-windows-amd64.exe .

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
    "$APP_DIR/" "$BUILD_DIR/automator/"

# Bundle shared systemd + nginx configs for the management plane
mkdir -p "$BUILD_DIR/packaging/linux-common"
cp packaging/linux-common/automator-reverb.service          "$BUILD_DIR/packaging/linux-common/"
cp packaging/linux-common/automator-scheduler.service        "$BUILD_DIR/packaging/linux-common/"
cp packaging/linux-common/automator-scheduler.timer          "$BUILD_DIR/packaging/linux-common/"
cp packaging/linux-common/automator-runner-sweep.service     "$BUILD_DIR/packaging/linux-common/"
cp packaging/linux-common/automator-runner-sweep.timer       "$BUILD_DIR/packaging/linux-common/"
cp packaging/linux-common/nginx-automator.conf                "$BUILD_DIR/packaging/linux-common/"

# Bundle distro-specific install/uninstall scripts for the management plane
mkdir -p "$BUILD_DIR/packaging/ubuntu" "$BUILD_DIR/packaging/rhel"
cp packaging/ubuntu/install.sh    "$BUILD_DIR/packaging/ubuntu/"
cp packaging/ubuntu/uninstall.sh  "$BUILD_DIR/packaging/ubuntu/"
cp packaging/rhel/install.sh      "$BUILD_DIR/packaging/rhel/"
cp packaging/rhel/uninstall.sh    "$BUILD_DIR/packaging/rhel/"

# Bundle the runner binaries + their own packaging
mkdir -p "$BUILD_DIR/runner/linux" "$BUILD_DIR/runner/windows"
cp "$RUNNER_DIR/automator-runner-linux-amd64"     "$BUILD_DIR/runner/linux/automator-runner"
chmod +x "$BUILD_DIR/runner/linux/automator-runner"
cp packaging/runner/linux/automator-runner.service "$BUILD_DIR/runner/linux/"
cp packaging/runner/linux/install.sh                "$BUILD_DIR/runner/linux/"
cp packaging/runner/linux/uninstall.sh              "$BUILD_DIR/runner/linux/"

cp "$RUNNER_DIR/automator-runner-windows-amd64.exe" "$BUILD_DIR/runner/windows/automator-runner.exe"
cp packaging/runner/windows/install.ps1             "$BUILD_DIR/runner/windows/"
cp packaging/runner/windows/uninstall.ps1           "$BUILD_DIR/runner/windows/"

# ---------------------------------------------------------------------------
# Publish both binaries to the management plane as draft releases. This
# never makes them live to the fleet by itself — that requires a separate,
# deliberate `automator:release-runner-binary` run (see docs/RUNNER_PROTOCOL.md).
# Best-effort: skipped if the app isn't set up yet (e.g. a fresh checkout
# with no configured database), since publishing isn't required to produce
# the distributable archive.
# ---------------------------------------------------------------------------
RUNNER_VERSION=$(grep -oP 'const Version = "\K[^"]+' "$RUNNER_DIR/version.go" 2>/dev/null) || true
if [ -n "$RUNNER_VERSION" ]; then
    info "Publishing automator-runner v${RUNNER_VERSION} to the management plane..."
    pushd "$APP_DIR" >/dev/null
    php artisan automator:publish-runner-binary "../$RUNNER_DIR/automator-runner-linux-amd64" --os=linux --arch=amd64 --runner-version="$RUNNER_VERSION" || \
        info "  (skipped — publish failed, likely no configured database yet)"
    php artisan automator:publish-runner-binary "../$RUNNER_DIR/automator-runner-windows-amd64.exe" --os=windows --arch=amd64 --runner-version="$RUNNER_VERSION" || \
        info "  (skipped — publish failed, likely no configured database yet)"
    popd >/dev/null
else
    info "Could not determine runner version — skipping publish."
fi

rm -f "$RUNNER_DIR/automator-runner-linux-amd64" "$RUNNER_DIR/automator-runner-windows-amd64.exe"

ARCHIVE="$OUTDIR/automator-${VERSION}-linux-x64.tar.gz"
info "Creating $ARCHIVE..."
tar -czf "$ARCHIVE" -C "$BUILD_DIR" .
info "  $(du -sh "$ARCHIVE" | cut -f1)  $ARCHIVE"

rm -rf "build"

info ""
info "Done. Package: $ARCHIVE"

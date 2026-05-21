#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Automator — build release archives
# Run from the repository root.
# Produces:
#   dist/automator-<version>-linux-x64.tar.gz
#   dist/automator-<version>-win-x64.zip
# ---------------------------------------------------------------------------

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

VERSION=$(grep '<Version>' src/Automator.Web/Automator.Web.csproj | sed 's/.*>\(.*\)<.*/\1/' | tr -d '[:space:]')
OUTDIR="dist"
BUILD_DIR="build"

info() { echo "[INFO]  $*"; }

info "Building Automator v${VERSION}..."
mkdir -p "$OUTDIR"

# ---------------------------------------------------------------------------
# Linux x64  (shared archive for Ubuntu and RHEL)
# ---------------------------------------------------------------------------
info "Publishing linux-x64..."
dotnet publish src/Automator.Web \
    -c Release \
    --self-contained true \
    -r linux-x64 \
    -o "$BUILD_DIR/linux-x64" \
    /p:PublishSingleFile=false \
    --nologo

# Bundle shared systemd + nginx configs
cp packaging/linux-common/automator.service    "$BUILD_DIR/linux-x64/"
cp packaging/linux-common/nginx-automator.conf "$BUILD_DIR/linux-x64/"

# Bundle distro-specific install/uninstall scripts
mkdir -p "$BUILD_DIR/linux-x64/packaging/ubuntu"
mkdir -p "$BUILD_DIR/linux-x64/packaging/rhel"
cp packaging/ubuntu/install.sh    "$BUILD_DIR/linux-x64/packaging/ubuntu/"
cp packaging/ubuntu/uninstall.sh  "$BUILD_DIR/linux-x64/packaging/ubuntu/"
cp packaging/rhel/install.sh      "$BUILD_DIR/linux-x64/packaging/rhel/"
cp packaging/rhel/uninstall.sh    "$BUILD_DIR/linux-x64/packaging/rhel/"

LINUX_ARCHIVE="$OUTDIR/automator-${VERSION}-linux-x64.tar.gz"
info "Creating $LINUX_ARCHIVE..."
tar -czf "$LINUX_ARCHIVE" -C "$BUILD_DIR/linux-x64" .
info "  $(du -sh "$LINUX_ARCHIVE" | cut -f1)  $LINUX_ARCHIVE"

# ---------------------------------------------------------------------------
# Windows x64
# ---------------------------------------------------------------------------
info "Publishing win-x64..."
dotnet publish src/Automator.Web \
    -c Release \
    --self-contained true \
    -r win-x64 \
    -o "$BUILD_DIR/win-x64" \
    /p:PublishSingleFile=false \
    --nologo

# Bundle install/uninstall scripts
cp packaging/windows/install.ps1   "$BUILD_DIR/win-x64/"
cp packaging/windows/uninstall.ps1 "$BUILD_DIR/win-x64/"

WIN_ARCHIVE="$OUTDIR/automator-${VERSION}-win-x64.zip"
info "Creating $WIN_ARCHIVE..."
(cd "$BUILD_DIR/win-x64" && zip -r "$REPO_ROOT/$WIN_ARCHIVE" . -q)
info "  $(du -sh "$WIN_ARCHIVE" | cut -f1)  $WIN_ARCHIVE"

# ---------------------------------------------------------------------------
# Cleanup build directory
# ---------------------------------------------------------------------------
rm -rf "$BUILD_DIR"

info ""
info "Done. Packages:"
ls -lh "$OUTDIR"/automator-"${VERSION}"-*.{tar.gz,zip} 2>/dev/null | awk '{print "  " $5 "  " $9}'

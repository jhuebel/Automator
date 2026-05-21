#Requires -RunAsAdministrator
<#
.SYNOPSIS
    Uninstalls Automator (Windows Service and IIS site).
#>
param(
    [switch]$KeepData
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "SilentlyContinue"

$ServiceName = "Automator"
$AppDir      = "C:\Program Files\Automator"
$DataDir     = "C:\ProgramData\Automator"
$IisSite     = "Automator"
$IisPool     = "Automator"

function Info  { param($msg) Write-Host "[INFO]  $msg" -ForegroundColor Cyan }
function Warn  { param($msg) Write-Host "[WARN]  $msg" -ForegroundColor Yellow }

# ---------------------------------------------------------------------------
# Stop and remove Windows Service
# ---------------------------------------------------------------------------
$svc = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
if ($svc) {
    if ($svc.Status -eq "Running") {
        Info "Stopping service '$ServiceName'..."
        Stop-Service -Name $ServiceName -Force
    }
    Info "Removing service '$ServiceName'..."
    sc.exe delete $ServiceName | Out-Null
} else {
    Warn "Service '$ServiceName' not found — skipping."
}

# ---------------------------------------------------------------------------
# Remove IIS site and app pool
# ---------------------------------------------------------------------------
if (Get-Module -ListAvailable -Name WebAdministration) {
    Import-Module WebAdministration

    if (Get-Website -Name $IisSite -ErrorAction SilentlyContinue) {
        Info "Removing IIS site '$IisSite'..."
        Stop-Website -Name $IisSite -ErrorAction SilentlyContinue
        Remove-Website -Name $IisSite
    }

    if (Test-Path "IIS:\AppPools\$IisPool") {
        Info "Removing IIS app pool '$IisPool'..."
        Remove-WebAppPool -Name $IisPool
    }
}

# ---------------------------------------------------------------------------
# Remove application files
# ---------------------------------------------------------------------------
Info "Removing application files..."
if (Test-Path $AppDir) { Remove-Item -Path $AppDir -Recurse -Force }

# ---------------------------------------------------------------------------
# Optionally remove data
# ---------------------------------------------------------------------------
if ($KeepData) {
    Warn "Data directory preserved at $DataDir (-KeepData was specified)."
} else {
    $reply = Read-Host "Remove data directory $DataDir (database, uploads)? [y/N]"
    if ($reply -match "^[Yy]$") {
        if (Test-Path $DataDir) {
            Remove-Item -Path $DataDir -Recurse -Force
            Info "Data directory removed."
        }
    } else {
        Warn "Data directory preserved at $DataDir."
    }
}

Info "Automator has been uninstalled."

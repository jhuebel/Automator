#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Installs the Automator Runner as a Windows service.

.DESCRIPTION
  Copies automator-runner.exe into Program Files, creates a restricted config
  directory under ProgramData, and registers a Windows service using NSSM
  (https://nssm.cc/) — a pragmatic v1 choice over a native Go Windows service
  integration. Requires nssm.exe to be available either on PATH or in the
  same directory as this script; it is not bundled here (download it
  yourself from nssm.cc and verify the checksum before running this script).

.EXAMPLE
  .\install.ps1
  Then register and start the service separately:
    & "C:\Program Files\AutomatorRunner\automator-runner.exe" register `
      --server https://automator.example.com --token <enrollment-token> `
      --name my-runner --tags windows `
      --config "C:\ProgramData\AutomatorRunner\config.json"
    Start-Service AutomatorRunner
#>

$ErrorActionPreference = "Stop"

$InstallDir = "C:\Program Files\AutomatorRunner"
$ConfigDir  = "C:\ProgramData\AutomatorRunner"
$ServiceName = "AutomatorRunner"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

function Find-Nssm {
    $local = Join-Path $ScriptDir "nssm.exe"
    if (Test-Path $local) { return $local }

    $onPath = Get-Command nssm.exe -ErrorAction SilentlyContinue
    if ($onPath) { return $onPath.Source }

    throw "nssm.exe not found next to this script or on PATH. Download it from https://nssm.cc/, verify it, and place nssm.exe alongside install.ps1."
}

if (-not (Test-Path (Join-Path $ScriptDir "automator-runner.exe"))) {
    throw "automator-runner.exe not found next to this script."
}

$nssm = Find-Nssm

Write-Host "[INFO] Installing binary to $InstallDir..."
New-Item -ItemType Directory -Force -Path $InstallDir | Out-Null
Copy-Item (Join-Path $ScriptDir "automator-runner.exe") (Join-Path $InstallDir "automator-runner.exe") -Force

Write-Host "[INFO] Creating config directory $ConfigDir..."
New-Item -ItemType Directory -Force -Path $ConfigDir | Out-Null

# Restrict the config directory (holds the runner's bearer token) to
# Administrators and SYSTEM only — the Windows analog of chmod 0600.
icacls $ConfigDir /inheritance:r | Out-Null
icacls $ConfigDir /grant:r "SYSTEM:(OI)(CI)F" | Out-Null
icacls $ConfigDir /grant:r "BUILTIN\Administrators:(OI)(CI)F" | Out-Null

$configPath = Join-Path $ConfigDir "config.json"
$exePath = Join-Path $InstallDir "automator-runner.exe"

Write-Host "[INFO] Registering Windows service '$ServiceName' via NSSM..."
& $nssm install $ServiceName $exePath run --config $configPath
& $nssm set $ServiceName AppDirectory $InstallDir
& $nssm set $ServiceName Start SERVICE_AUTO_START
& $nssm set $ServiceName AppRestartDelay 5000
& $nssm set $ServiceName AppExit Default Restart

Write-Host ""
Write-Host "[INFO] Installed. Register this runner, then start the service:"
Write-Host ""
Write-Host "  & `"$exePath`" register --server https://your-automator-host \"
Write-Host "    --token <enrollment-token-from-Settings> --name $env:COMPUTERNAME \"
Write-Host "    --tags windows --config `"$configPath`""
Write-Host ""
Write-Host "  Start-Service $ServiceName"

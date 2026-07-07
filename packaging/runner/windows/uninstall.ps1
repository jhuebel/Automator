#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Uninstalls the Automator Runner Windows service.
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
    return $null
}

$configPath = Join-Path $ConfigDir "config.json"
$exePath = Join-Path $InstallDir "automator-runner.exe"

if (Test-Path $configPath) {
    Write-Host "[INFO] Unregistering from the management plane..."
    try {
        & $exePath unregister --config $configPath
    } catch {
        Write-Host "[WARN] Unregister call failed (server unreachable?) — remove the runner manually from Settings > Runners."
    }
}

$nssm = Find-Nssm
if ($nssm) {
    Write-Host "[INFO] Removing Windows service '$ServiceName'..."
    & $nssm stop $ServiceName 2>$null
    & $nssm remove $ServiceName confirm
} else {
    Write-Host "[WARN] nssm.exe not found — remove the '$ServiceName' service manually (sc.exe delete $ServiceName)."
}

Write-Host "[INFO] Removing files..."
Remove-Item -Recurse -Force $InstallDir -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force $ConfigDir -ErrorAction SilentlyContinue

Write-Host "[INFO] Automator Runner has been uninstalled."

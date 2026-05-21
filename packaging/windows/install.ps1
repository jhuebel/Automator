#Requires -RunAsAdministrator
<#
.SYNOPSIS
    Installs Automator as a Windows Service with an IIS reverse proxy.

.DESCRIPTION
    Extracts the win-x64 archive, installs the app as a Windows Service,
    and optionally creates an IIS website that proxies port 80 to the service.

    Prerequisites:
    - Windows Server 2019 / 2022 or Windows 10/11 (x64)
    - IIS with URL Rewrite 2.1 and Application Request Routing 3.0
      (required only for the IIS proxy; install.ps1 checks and warns)

.PARAMETER Archive
    Path to the automator-*-win-x64.zip archive.
    Defaults to the first matching archive in the script directory.

.PARAMETER SkipIis
    Skip IIS site creation (the service still listens on port 5000).
#>
param(
    [string]$Archive = "",
    [switch]$SkipIis
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# ---------------------------------------------------------------------------
# Paths
# ---------------------------------------------------------------------------
$AppDir      = "C:\Program Files\Automator\app"
$DataDir     = "C:\ProgramData\Automator"
$ServiceName = "Automator"
$ScriptDir   = Split-Path -Parent $MyInvocation.MyCommand.Definition

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
function Info  { param($msg) Write-Host "[INFO]  $msg" -ForegroundColor Cyan }
function Warn  { param($msg) Write-Host "[WARN]  $msg" -ForegroundColor Yellow }
function Error { param($msg) Write-Host "[ERROR] $msg" -ForegroundColor Red; exit 1 }

# ---------------------------------------------------------------------------
# Locate archive
# ---------------------------------------------------------------------------
if (-not $Archive) {
    $Archive = Get-ChildItem -Path $ScriptDir -Filter "automator-*-win-x64.zip" |
               Sort-Object Name | Select-Object -Last 1 -ExpandProperty FullName
}
if (-not $Archive -or -not (Test-Path $Archive)) {
    Error "Archive not found. Pass -Archive <path> or place the .zip in the same directory as this script."
}

# ---------------------------------------------------------------------------
# Create directories
# ---------------------------------------------------------------------------
Info "Creating application directories..."
New-Item -ItemType Directory -Force -Path $AppDir  | Out-Null
New-Item -ItemType Directory -Force -Path $DataDir | Out-Null

# ---------------------------------------------------------------------------
# Extract archive
# ---------------------------------------------------------------------------
Info "Extracting application archive..."
Expand-Archive -Path $Archive -DestinationPath $AppDir -Force

# ---------------------------------------------------------------------------
# Write appsettings.Production.json
# ---------------------------------------------------------------------------
Info "Writing appsettings.Production.json..."
$Config = @{
    ConnectionStrings = @{
        DefaultConnection = "Data Source=$DataDir\automator.db"
    }
} | ConvertTo-Json -Depth 3
Set-Content -Path "$AppDir\appsettings.Production.json" -Value $Config -Encoding UTF8

# ---------------------------------------------------------------------------
# Install Windows Service
# ---------------------------------------------------------------------------
Info "Installing Windows Service '$ServiceName'..."
$ExePath = "$AppDir\Automator.Web.exe"
if (-not (Test-Path $ExePath)) {
    Error "Executable not found at $ExePath — check the archive contents."
}

$existing = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
if ($existing) {
    Warn "Service '$ServiceName' already exists — stopping and removing it first."
    Stop-Service -Name $ServiceName -Force -ErrorAction SilentlyContinue
    sc.exe delete $ServiceName | Out-Null
    Start-Sleep -Seconds 2
}

sc.exe create $ServiceName binPath= "`"$ExePath`"" start= auto DisplayName= "Automator" | Out-Null
sc.exe description $ServiceName "Automator — IaaS script manager" | Out-Null

# Set environment variables via registry
$RegPath = "HKLM:\SYSTEM\CurrentControlSet\Services\$ServiceName"
$EnvVars = @(
    "ASPNETCORE_URLS=http://127.0.0.1:5000",
    "ASPNETCORE_ENVIRONMENT=Production"
)
New-ItemProperty -Path $RegPath -Name "Environment" -Value $EnvVars -PropertyType MultiString -Force | Out-Null

Info "Starting service..."
Start-Service -Name $ServiceName
$svc = Get-Service -Name $ServiceName
if ($svc.Status -ne "Running") {
    Error "Service failed to start. Check the Windows Event Log for details."
}
Info "Service is running."

# ---------------------------------------------------------------------------
# IIS configuration
# ---------------------------------------------------------------------------
if ($SkipIis) {
    Warn "Skipping IIS configuration (-SkipIis). The app listens on http://127.0.0.1:5000."
} else {
    Info "Checking IIS prerequisites..."

    $iisOk = $true

    if (-not (Get-Module -ListAvailable -Name WebAdministration)) {
        Warn "IIS WebAdministration module not found — skipping IIS setup. Install IIS first."
        $iisOk = $false
    }

    if ($iisOk) {
        Import-Module WebAdministration

        # Check for URL Rewrite
        $urlRewrite = Get-WebGlobalModule -Name "RewriteModule" -ErrorAction SilentlyContinue
        if (-not $urlRewrite) {
            Warn "IIS URL Rewrite 2.1 is not installed. Download from https://www.iis.net/downloads/microsoft/url-rewrite"
            $iisOk = $false
        }

        # Check for ARR
        $arr = Get-WebGlobalModule -Name "ApplicationRequestRouting" -ErrorAction SilentlyContinue
        if (-not $arr) {
            Warn "IIS Application Request Routing 3.0 is not installed. Download from https://www.iis.net/downloads/microsoft/application-request-routing"
            $iisOk = $false
        }
    }

    if ($iisOk) {
        Info "Configuring IIS..."

        # Enable ARR proxy at server level
        Set-WebConfigurationProperty -Filter "system.webServer/proxy" -Name "enabled" -Value $true -PSPath "IIS:\" 2>$null | Out-Null

        $SiteName  = "Automator"
        $PoolName  = "Automator"
        $SiteRoot  = "$env:SystemDrive\inetpub\automator"
        New-Item -ItemType Directory -Force -Path $SiteRoot | Out-Null

        # App pool
        if (-not (Test-Path "IIS:\AppPools\$PoolName")) {
            New-WebAppPool -Name $PoolName | Out-Null
        }
        Set-ItemProperty "IIS:\AppPools\$PoolName" processModel.identityType LocalSystem

        # Remove existing site on port 80 if it's the Default Web Site
        $defaultSite = Get-Website -Name "Default Web Site" -ErrorAction SilentlyContinue
        if ($defaultSite -and ($defaultSite.Bindings.Collection | Where-Object { $_.bindingInformation -like "*:80:*" })) {
            Warn "Stopping 'Default Web Site' to free port 80..."
            Stop-Website -Name "Default Web Site"
        }

        # Create site
        if (Get-Website -Name $SiteName -ErrorAction SilentlyContinue) {
            Remove-Website -Name $SiteName
        }
        New-Website -Name $SiteName -PhysicalPath $SiteRoot -ApplicationPool $PoolName -Port 80 | Out-Null

        # Write web.config with ARR reverse proxy rule
        $WebConfig = @"
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <rewrite>
      <rules>
        <rule name="ReverseProxy" stopProcessing="true">
          <match url="(.*)" />
          <action type="Rewrite" url="http://127.0.0.1:5000/{R:1}" />
        </rule>
      </rules>
    </rewrite>
    <proxy enabled="true" preserveHostHeader="true" reverseRewriteHostInResponseHeaders="false" />
  </system.webServer>
</configuration>
"@
        Set-Content -Path "$SiteRoot\web.config" -Value $WebConfig -Encoding UTF8

        Start-Website -Name $SiteName
        Info "IIS site '$SiteName' created on port 80."
    } else {
        Warn "IIS setup skipped due to missing prerequisites. The app is accessible at http://127.0.0.1:5000."
    }
}

# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------
Info ""
Info "Automator installed successfully!"
Info "  Service: $ServiceName (Windows Services)"
if (-not $SkipIis) {
    Info "  App:     http://localhost"
} else {
    Info "  App:     http://localhost:5000"
}
Info "  Data:    $DataDir"
Info ""
Warn "Default credentials are admin/Admin1234! — change them immediately."

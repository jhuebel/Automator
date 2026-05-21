# Installation

## Pre-built Packages

Download the latest release archive for your platform from the [Releases page](https://github.com/jhuebel/Automator/releases).

### Ubuntu

```bash
# 1. Extract the archive and run the Ubuntu install script
tar -xzf automator-<version>-linux-x64.tar.gz -C /tmp/automator-install
sudo bash /tmp/automator-install/packaging/ubuntu/install.sh
```

### RHEL / Rocky / AlmaLinux

```bash
# 1. Extract the archive and run the RHEL install script
tar -xzf automator-<version>-linux-x64.tar.gz -C /tmp/automator-install
sudo bash /tmp/automator-install/packaging/rhel/install.sh
```

> **SELinux note:** The RHEL install script automatically runs `setsebool -P httpd_can_network_connect 1` to allow nginx to proxy to the app. If SELinux is not present the command is skipped.

### Windows Server

Requires IIS with [URL Rewrite 2.1](https://www.iis.net/downloads/microsoft/url-rewrite) and [Application Request Routing 3.0](https://www.iis.net/downloads/microsoft/application-request-routing) for the reverse proxy. Run in an elevated PowerShell session:

```powershell
Expand-Archive automator-<version>-win-x64.zip -DestinationPath C:\automator-install
Set-ExecutionPolicy -Scope Process Bypass
C:\automator-install\install.ps1
```

Pass `-SkipIis` to install only the Windows Service (app listens on port 5000 directly).

### Installed paths (Linux)

| Path | Contents |
|---|---|
| `/opt/automator/app` | Application binary and assets |
| `/opt/automator/data` | SQLite database (`automator.db`) |
| `/etc/automator/environment` | Optional secrets/env-var overrides (create manually) |
| `/etc/systemd/system/automator.service` | Systemd unit |
| `/etc/nginx/conf.d/automator.conf` | nginx reverse proxy config |

### Installed paths (Windows)

| Path | Contents |
|---|---|
| `C:\Program Files\Automator\app` | Application binary and assets |
| `C:\ProgramData\Automator` | SQLite database |

### Uninstall

```bash
# Ubuntu
sudo bash packaging/ubuntu/uninstall.sh

# RHEL
sudo bash packaging/rhel/uninstall.sh
```

```powershell
# Windows (elevated PowerShell)
C:\automator-install\uninstall.ps1
```

---

## Build from Source

### Prerequisites

- [.NET 9 SDK](https://dot.net)
- The scripting runtimes you intend to use (`bash`, `pwsh`, `python3`, `ansible-playbook`, `terraform`)

## Install .NET 9 (Linux — no root required)

```bash
curl -sSL https://dot.net/v1/dotnet-install.sh | bash -s -- --channel 9.0
echo 'export DOTNET_ROOT="$HOME/.dotnet"' >> ~/.bashrc
echo 'export PATH="$HOME/.dotnet:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

## Run the App

```bash
git clone https://github.com/jhuebel/Automator.git
cd Automator
dotnet run --project src/Automator.Web
```

Open **http://localhost:5000** in your browser.

## First Run

On first run Automator creates `automator.db` in the working directory, seeds the four roles, and creates default users. **Change these credentials immediately in a production deployment.**

| Username | Password | Role |
|---|---|---|
| `admin` | `Admin1234!` | Admin |
| `operator` | `Operator1234!` | Operator |
| `viewer` | `Viewer1234!` | Viewer |

Default credentials can be overridden via `appsettings.json` or environment variables before first run:

```json
"DefaultAdmin": { "Username": "myadmin", "Email": "admin@example.com", "Password": "MyStr0ng!" }
```

## Data File Location

By default `automator.db` is created in the working directory. To change it:

```json
"ConnectionStrings": {
  "DefaultConnection": "Data Source=/var/lib/automator/automator.db"
}
```

## MySQL / MariaDB

Set `DatabaseProvider` to `MySQL` (also accepted: `MariaDB`) and provide a connection string:

```json
"DatabaseProvider": "MySQL",
"ConnectionStrings": {
  "DefaultConnection": "Server=localhost;Database=automator;User=automator;Password=secret;"
}
```

The database and user must exist before first run; Automator creates all tables automatically. The MySQL user needs `CREATE`, `ALTER`, `INDEX`, `SELECT`, `INSERT`, `UPDATE`, and `DELETE` privileges on the database.

Environment variables work too (useful in containers):

```bash
DatabaseProvider=MySQL
ConnectionStrings__DefaultConnection="Server=db;Database=automator;User=automator;Password=secret;"
```

## Running as a Service (Linux — systemd)

```ini
[Unit]
Description=Automator
After=network.target

[Service]
WorkingDirectory=/opt/automator
ExecStart=/home/<user>/.dotnet/dotnet /opt/automator/Automator.Web.dll
Restart=always
User=<user>
Environment=ASPNETCORE_URLS=http://0.0.0.0:5000

[Install]
WantedBy=multi-user.target
```

Publish a self-contained build first:

```bash
dotnet publish src/Automator.Web -c Release -o /opt/automator
```

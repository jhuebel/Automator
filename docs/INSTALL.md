# Installation

## Pre-built Packages

Download the latest release archive for your platform from the [Releases page](https://github.com/jhuebel/Automator/releases).

### Ubuntu

```bash
mkdir automator && tar -xzf automator-<version>-linux-x64.tar.gz -C automator
cd automator
sudo bash packaging/ubuntu/install.sh
```

The install script:
- Installs `nginx` via `apt-get`
- Creates an `automator` system user
- Copies the app to `/opt/automator/app`
- Creates a systemd service and enables it at boot
- Writes an nginx reverse proxy config to `/etc/nginx/conf.d/automator.conf`

### RHEL / Rocky / AlmaLinux

```bash
mkdir automator && tar -xzf automator-<version>-linux-x64.tar.gz -C automator
cd automator
sudo bash packaging/rhel/install.sh
```

Same as Ubuntu, but uses `dnf`, applies `setsebool -P httpd_can_network_connect 1` for SELinux, and opens the HTTP port via `firewall-cmd` if firewalld is active.

### Windows Server

Requires IIS with [URL Rewrite 2.1](https://www.iis.net/downloads/microsoft/url-rewrite) and [Application Request Routing 3.0](https://www.iis.net/downloads/microsoft/application-request-routing) for the reverse proxy. Run in an elevated PowerShell session:

```powershell
Expand-Archive automator-<version>-win-x64.zip -DestinationPath C:\automator-install
Set-ExecutionPolicy -Scope Process Bypass
C:\automator-install\install.ps1
```

The install script:
- Copies the app to `C:\Program Files\Automator\app`
- Installs it as a Windows Service with `ASPNETCORE_ENVIRONMENT=Production`
- Creates an IIS site on port 80 with an ARR reverse proxy rule

Pass `-SkipIis` to install only the Windows Service (app listens on `http://127.0.0.1:5000` directly).

### Installed paths

**Linux**

| Path | Contents |
|---|---|
| `/opt/automator/app` | Application binary and assets |
| `/opt/automator/data` | SQLite database (`automator.db`) |
| `/etc/automator/environment` | Optional env-var overrides — create this file manually |
| `/etc/systemd/system/automator.service` | Systemd unit |
| `/etc/nginx/conf.d/automator.conf` | nginx reverse proxy config |

**Windows**

| Path | Contents |
|---|---|
| `C:\Program Files\Automator\app` | Application binary and assets |
| `C:\ProgramData\Automator` | SQLite database |

### Uninstall

**Linux** — re-extract the archive and run the uninstall script:

```bash
mkdir automator && tar -xzf automator-<version>-linux-x64.tar.gz -C automator
cd automator
sudo bash packaging/ubuntu/uninstall.sh   # or packaging/rhel/uninstall.sh
```

**Windows** (elevated PowerShell):

```powershell
C:\automator-install\uninstall.ps1
```

---

## Build from Source

### Prerequisites

- [.NET 9 SDK](https://dot.net)
- Scripting runtimes you intend to use: `bash`, `pwsh`, `python3`, `ansible-playbook`, `terraform`

### Install .NET 9 SDK (Linux — no root required)

```bash
curl -sSL https://dot.net/v1/dotnet-install.sh | bash -s -- --channel 9.0
echo 'export DOTNET_ROOT="$HOME/.dotnet"' >> ~/.bashrc
echo 'export PATH="$HOME/.dotnet:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

### Run the App

```bash
git clone https://github.com/jhuebel/Automator.git
cd Automator
dotnet run --project src/Automator.Web
```

Open **http://localhost:5000** in your browser.

### Build Release Archives

```bash
bash packaging/build.sh
```

Produces `dist/automator-<version>-linux-x64.tar.gz` and `dist/automator-<version>-win-x64.zip`.

---

## First Run

On first run Automator creates `automator.db` and seeds roles and default users. **Change these credentials immediately in a production deployment.**

| Username | Password | Role |
|---|---|---|
| `admin` | `Admin1234!` | Admin |
| `operator` | `Operator1234!` | Operator |
| `viewer` | `Viewer1234!` | Viewer |

Default credentials can be overridden via `appsettings.json` before first run:

```json
"DefaultAdmin": { "Username": "myadmin", "Email": "admin@example.com", "Password": "MyStr0ng!" }
```

---

## Configuration

### Data File Location

By default `automator.db` is created in the working directory. Pre-built packages write it to `/opt/automator/data/automator.db` (Linux) or `C:\ProgramData\Automator\automator.db` (Windows). To change it, set the connection string:

```json
"ConnectionStrings": {
  "DefaultConnection": "Data Source=/var/lib/automator/automator.db"
}
```

### MySQL / MariaDB

Set `DatabaseProvider` to `MySQL` (also accepted: `MariaDB`) and provide a connection string:

```json
"DatabaseProvider": "MySQL",
"ConnectionStrings": {
  "DefaultConnection": "Server=localhost;Database=automator;User=automator;Password=secret;"
}
```

The database and user must exist before first run; Automator creates all tables automatically. The MySQL user needs `CREATE`, `ALTER`, `INDEX`, `SELECT`, `INSERT`, `UPDATE`, and `DELETE` privileges.

Environment variables work too (useful in containers or for secrets):

```bash
DatabaseProvider=MySQL
ConnectionStrings__DefaultConnection="Server=db;Database=automator;User=automator;Password=secret;"
```

On Linux, place these in `/etc/automator/environment` — the systemd unit loads it automatically via `EnvironmentFile`.

### Secrets Override (Linux)

Create `/etc/automator/environment` to set any configuration value as an environment variable without editing `appsettings.json`:

```bash
# /etc/automator/environment
ConnectionStrings__DefaultConnection="Data Source=/mnt/nas/automator.db"
```

Restart the service after editing: `systemctl restart automator`.

---

## Manual Service Installation

Use this section if you built from source and want to deploy without the pre-built packaging scripts.

### Linux — systemd + nginx

Publish a self-contained binary:

```bash
dotnet publish src/Automator.Web -c Release --self-contained true -r linux-x64 -o /opt/automator/app
```

Create a systemd unit at `/etc/systemd/system/automator.service`:

```ini
[Unit]
Description=Automator
After=network.target

[Service]
Type=simple
User=automator
Group=automator
WorkingDirectory=/opt/automator/app
ExecStart=/opt/automator/app/Automator.Web
Restart=on-failure
EnvironmentFile=-/etc/automator/environment
Environment=ASPNETCORE_URLS=http://127.0.0.1:5000
Environment=ASPNETCORE_ENVIRONMENT=Production
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=full
ReadWritePaths=/opt/automator/data

[Install]
WantedBy=multi-user.target
```

Create an nginx reverse proxy config at `/etc/nginx/conf.d/automator.conf`:

```nginx
server {
    listen 80;
    server_name _;

    location / {
        proxy_pass         http://127.0.0.1:5000;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade $http_upgrade;
        proxy_set_header   Connection "upgrade";
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_read_timeout 3600s;
    }
}
```

Enable and start:

```bash
systemctl daemon-reload
systemctl enable --now automator
nginx -t && systemctl restart nginx
```

On RHEL / Rocky / AlmaLinux, also run:

```bash
setsebool -P httpd_can_network_connect 1      # SELinux
firewall-cmd --permanent --add-service=http   # firewalld
firewall-cmd --reload
```

### Windows — Windows Service

Publish a self-contained binary:

```powershell
dotnet publish src/Automator.Web -c Release --self-contained true -r win-x64 -o "C:\Program Files\Automator\app"
```

Register the Windows Service:

```powershell
sc.exe create Automator binPath= '"C:\Program Files\Automator\app\Automator.Web.exe"' start= auto
# Set environment variables
$reg = "HKLM:\SYSTEM\CurrentControlSet\Services\Automator"
New-ItemProperty $reg -Name Environment -Value @(
    "ASPNETCORE_URLS=http://127.0.0.1:5000",
    "ASPNETCORE_ENVIRONMENT=Production"
) -PropertyType MultiString -Force
Start-Service Automator
```

Configure IIS as a reverse proxy using URL Rewrite 2.1 and Application Request Routing 3.0. See the `packaging/windows/install.ps1` script for a complete example.

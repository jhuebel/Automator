using Automator.Web.Models;

namespace Automator.Web.Data;

public static class DataSeeder
{
    public static void Seed(AutomatorDbContext db)
    {
        var scripts = BuildScripts();
        db.Scripts.AddRange(scripts);
        db.SaveChanges();

        var sysInfoBash = scripts.First(s => s.Name == "System Info (Bash)");
        var diskCheck = scripts.First(s => s.Name == "Disk Space Alert (PowerShell)");

        db.ScheduledJobs.AddRange(
            new ScheduledJob
            {
                Name = "Hourly System Info",
                ScriptId = sysInfoBash.Id,
                CronExpression = "0 * * * *",
                IsEnabled = false,
                NextRunAt = NextOccurrence("0 * * * *")
            },
            new ScheduledJob
            {
                Name = "Daily Disk Check",
                ScriptId = diskCheck.Id,
                CronExpression = "0 8 * * *",
                IsEnabled = false,
                NextRunAt = NextOccurrence("0 8 * * *")
            }
        );
        db.SaveChanges();
    }

    private static DateTime? NextOccurrence(string cron)
    {
        try
        {
            var schedule = Cronos.CronExpression.Parse(cron, Cronos.CronFormat.Standard);
            return schedule.GetNextOccurrence(DateTime.UtcNow, TimeZoneInfo.Utc);
        }
        catch { return null; }
    }

    private static List<ScriptDefinition> BuildScripts() =>
    [
        new()
        {
            Name = "System Info (Bash)",
            Description = "Displays CPU, memory, and disk information",
            Language = ScriptLanguage.Bash,
            Tags = ["system", "diagnostics", "linux"],
            Content = """
                #!/bin/bash
                echo "=== System Information ==="
                echo "Hostname:  $(hostname)"
                echo "OS:        $(uname -srm)"
                echo "Uptime:    $(uptime -p 2>/dev/null || uptime)"
                echo "CPU Cores: $(nproc)"
                echo ""
                echo "=== Memory ==="
                free -h
                echo ""
                echo "=== Disk Usage ==="
                df -h --output=target,size,used,avail,pcent 2>/dev/null | grep -v tmpfs || df -h
                """
        },
        new()
        {
            Name = "System Info (PowerShell)",
            Description = "Displays system information via PowerShell Core (cross-platform)",
            Language = ScriptLanguage.PowerShell,
            Tags = ["system", "diagnostics", "windows", "linux"],
            Content = """
                Write-Host "=== System Information ===" -ForegroundColor Cyan
                Write-Host "Hostname:  $([System.Net.Dns]::GetHostName())"
                Write-Host "OS:        $([System.Environment]::OSVersion.VersionString)"
                Write-Host "Runtime:   $([System.Runtime.InteropServices.RuntimeInformation]::OSDescription)"
                Write-Host "CPU Cores: $([System.Environment]::ProcessorCount)"
                Write-Host ""
                Write-Host "=== Memory ===" -ForegroundColor Cyan
                if ($IsWindows) {
                    $os = Get-CimInstance Win32_OperatingSystem
                    $totalGB = [math]::Round($os.TotalVisibleMemorySize / 1MB, 2)
                    $freeGB  = [math]::Round($os.FreePhysicalMemory / 1MB, 2)
                    Write-Host "Total: ${totalGB} GB   Free: ${freeGB} GB"
                } else {
                    Get-Content /proc/meminfo | Select-String -Pattern "MemTotal|MemFree|MemAvailable"
                }
                """
        },
        new()
        {
            Name = "Python Environment Check",
            Description = "Reports Python version, platform, and installed packages",
            Language = ScriptLanguage.Python,
            Tags = ["python", "diagnostics", "environment"],
            Content = """
                import sys, platform, subprocess

                print(f"Python:    {sys.version}")
                print(f"Platform:  {platform.platform()}")
                print(f"Machine:   {platform.machine()}")
                print(f"Processor: {platform.processor()}")
                print()

                result = subprocess.run(
                    [sys.executable, "-m", "pip", "list", "--format=columns"],
                    capture_output=True, text=True
                )
                if result.returncode == 0:
                    print("Installed packages:")
                    print(result.stdout)
                else:
                    print(f"pip error: {result.stderr}")
                """
        },
        new()
        {
            Name = "Network Connectivity Check (Bash)",
            Description = "Pings common DNS servers and checks external connectivity",
            Language = ScriptLanguage.Bash,
            Tags = ["network", "diagnostics", "linux"],
            Content = """
                #!/bin/bash
                TARGETS=("8.8.8.8" "1.1.1.1" "9.9.9.9")
                echo "=== Network Connectivity Check ==="
                for target in "${TARGETS[@]}"; do
                    if ping -c 1 -W 2 "$target" &>/dev/null; then
                        echo "[OK]   $target is reachable"
                    else
                        echo "[FAIL] $target is unreachable"
                    fi
                done
                echo ""
                echo "=== DNS Resolution ==="
                for host in "google.com" "github.com" "microsoft.com"; do
                    ip=$(dig +short "$host" 2>/dev/null | head -1 || nslookup "$host" 2>/dev/null | awk '/^Address: / { print $2 }' | head -1)
                    if [ -n "$ip" ]; then
                        echo "[OK]   $host -> $ip"
                    else
                        echo "[FAIL] $host could not be resolved"
                    fi
                done
                """
        },
        new()
        {
            Name = "Disk Space Alert (PowerShell)",
            Description = "Reports drives with less than 20% free space",
            Language = ScriptLanguage.PowerShell,
            Tags = ["storage", "monitoring", "windows", "linux"],
            Content = """
                $threshold = 20
                Write-Host "=== Disk Space Report (threshold: ${threshold}% free) ===" -ForegroundColor Cyan
                Write-Host ""

                if ($IsWindows) {
                    $drives = Get-PSDrive -PSProvider FileSystem | Where-Object { $_.Used -gt 0 }
                    foreach ($drive in $drives) {
                        $total = $drive.Used + $drive.Free
                        $pctFree = [math]::Round(($drive.Free / $total) * 100, 1)
                        $status = if ($pctFree -lt $threshold) { "ALERT" } else { "OK" }
                        $color  = if ($pctFree -lt $threshold) { "Red" } else { "Green" }
                        Write-Host "[$status] $($drive.Name): ${pctFree}% free" -ForegroundColor $color
                    }
                } else {
                    df -h | tail -n +2 | while read -r line; do
                        echo $line
                    done
                }
                """
        }
    ];
}

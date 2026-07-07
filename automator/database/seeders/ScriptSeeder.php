<?php

namespace Database\Seeders;

use App\Enums\ScriptLanguage;
use App\Models\ScriptDefinition;
use Illuminate\Database\Seeder;

class ScriptSeeder extends Seeder
{
    public function run(): void
    {
        if (ScriptDefinition::query()->exists()) {
            return;
        }

        foreach ($this->scripts() as $script) {
            ScriptDefinition::create($script);
        }
    }

    private function scripts(): array
    {
        return [
            [
                'name' => 'System Info (Bash)',
                'description' => 'Displays CPU, memory, and disk information',
                'language' => ScriptLanguage::Bash,
                'tags' => ['system', 'diagnostics', 'linux'],
                'content' => <<<'BASH'
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
                    BASH,
            ],
            [
                'name' => 'System Info (PowerShell)',
                'description' => 'Displays system information via PowerShell Core (cross-platform)',
                'language' => ScriptLanguage::PowerShell,
                'tags' => ['system', 'diagnostics', 'windows', 'linux'],
                'content' => <<<'POWERSHELL'
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
                    POWERSHELL,
            ],
            [
                'name' => 'Python Environment Check',
                'description' => 'Reports Python version, platform, and installed packages',
                'language' => ScriptLanguage::Python,
                'tags' => ['python', 'diagnostics', 'environment'],
                'content' => <<<'PYTHON'
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
                    PYTHON,
            ],
            [
                'name' => 'Network Connectivity Check (Bash)',
                'description' => 'Pings common DNS servers and checks external connectivity',
                'language' => ScriptLanguage::Bash,
                'tags' => ['network', 'diagnostics', 'linux'],
                'content' => <<<'BASH'
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
                    BASH,
            ],
            [
                'name' => 'Hello World (Terraform)',
                'description' => 'Minimal Terraform configuration that prints a greeting via a local output',
                'language' => ScriptLanguage::Terraform,
                'tags' => ['terraform', 'demo', 'hello-world'],
                'content' => <<<'TF'
                    terraform {
                      required_version = ">= 1.0"
                    }

                    variable "greeting" {
                      type    = string
                      default = "Hello, World!"
                    }

                    output "message" {
                      value = var.greeting
                    }
                    TF,
            ],
            [
                'name' => 'Disk Space Alert (PowerShell)',
                'description' => 'Reports drives with less than 20% free space',
                'language' => ScriptLanguage::PowerShell,
                'tags' => ['storage', 'monitoring', 'windows', 'linux'],
                'content' => <<<'POWERSHELL'
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
                    POWERSHELL,
            ],
        ];
    }
}

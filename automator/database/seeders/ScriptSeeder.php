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
            [
                'name' => 'ANSI Color Palette (Bash)',
                'description' => 'Prints the standard 16-color ANSI palette plus text styles — useful for checking that the output terminal renders color correctly',
                'language' => ScriptLanguage::Bash,
                'tags' => ['demo', 'ansi', 'colors'],
                'content' => <<<'BASH'
                    #!/bin/bash
                    echo "=== Standard Foreground Colors (30-37) ==="
                    for i in 30 31 32 33 34 35 36 37; do
                        printf "\033[%sm Color %s \033[0m" "$i" "$i"
                    done
                    echo ""
                    echo ""

                    echo "=== Bright Foreground Colors (90-97) ==="
                    for i in 90 91 92 93 94 95 96 97; do
                        printf "\033[%sm Color %s \033[0m" "$i" "$i"
                    done
                    echo ""
                    echo ""

                    echo "=== Background Colors (40-47) ==="
                    for i in 40 41 42 43 44 45 46 47; do
                        printf "\033[%sm    \033[0m" "$i"
                    done
                    echo ""
                    echo ""

                    echo "=== Text Styles ==="
                    printf "\033[1mBold\033[0m  "
                    printf "\033[2mDim\033[0m  "
                    printf "\033[3mItalic\033[0m  "
                    printf "\033[4mUnderline\033[0m  "
                    printf "\033[5mBlink\033[0m  "
                    printf "\033[7mReverse\033[0m\n"
                    BASH,
            ],
            [
                'name' => 'ANSI Color Palette (PowerShell)',
                'description' => 'Prints the standard 16-color ANSI palette plus text styles via $PSStyle (PowerShell 7.2+) — useful for checking that the output terminal renders color correctly',
                'language' => ScriptLanguage::PowerShell,
                'tags' => ['demo', 'ansi', 'colors'],
                'content' => <<<'POWERSHELL'
                    $colors = 'Black', 'Red', 'Green', 'Yellow', 'Blue', 'Magenta', 'Cyan', 'White'

                    Write-Host "=== Standard Foreground Colors ==="
                    foreach ($name in $colors) {
                        Write-Host "$($PSStyle.Foreground.$name) $name $($PSStyle.Reset)" -NoNewline
                    }
                    Write-Host "`n"

                    Write-Host "=== Bright Foreground Colors ==="
                    foreach ($name in $colors) {
                        $bright = "Bright$name"
                        Write-Host "$($PSStyle.Foreground.$bright) $name $($PSStyle.Reset)" -NoNewline
                    }
                    Write-Host "`n"

                    Write-Host "=== Background Colors ==="
                    foreach ($name in $colors) {
                        Write-Host "$($PSStyle.Background.$name)    $($PSStyle.Reset)" -NoNewline
                    }
                    Write-Host "`n"

                    Write-Host "=== Text Styles ==="
                    Write-Host "$($PSStyle.Bold)Bold$($PSStyle.BoldOff)  " -NoNewline
                    Write-Host "$($PSStyle.Dim)Dim$($PSStyle.DimOff)  " -NoNewline
                    Write-Host "$($PSStyle.Italic)Italic$($PSStyle.ItalicOff)  " -NoNewline
                    Write-Host "$($PSStyle.Underline)Underline$($PSStyle.UnderlineOff)  " -NoNewline
                    Write-Host "$($PSStyle.Blink)Blink$($PSStyle.BlinkOff)  " -NoNewline
                    Write-Host "$($PSStyle.Reverse)Reverse$($PSStyle.ReverseOff)"
                    POWERSHELL,
            ],
            [
                'name' => 'Colored Status Report (Bash)',
                'description' => 'A mock health-check report using semantic color (green/yellow/red) — a realistic example of why ANSI rendering matters, not just a color chart',
                'language' => ScriptLanguage::Bash,
                'tags' => ['demo', 'ansi', 'colors', 'monitoring'],
                'content' => <<<'BASH'
                    #!/bin/bash
                    GREEN='\033[32m'
                    RED='\033[31m'
                    YELLOW='\033[33m'
                    BOLD='\033[1m'
                    RESET='\033[0m'

                    echo -e "${BOLD}=== Service Health Check ===${RESET}"
                    echo -e "${GREEN}[PASS]${RESET} API gateway responding (200 OK, 42ms)"
                    echo -e "${GREEN}[PASS]${RESET} Database connection healthy"
                    echo -e "${YELLOW}[WARN]${RESET} Cache hit rate below target (68%, target 80%)"
                    echo -e "${RED}[FAIL]${RESET} Background queue worker not responding"
                    echo -e "${GREEN}[PASS]${RESET} Disk usage within limits (54% used)"
                    echo ""
                    echo -e "${BOLD}Summary:${RESET} 3 passed, 1 warning, 1 failed"
                    BASH,
            ],
        ];
    }
}

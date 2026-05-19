using System.Diagnostics;
using System.Runtime.InteropServices;
using Automator.Web.Models;

namespace Automator.Web.Services;

public class ScriptRunnerService : IScriptRunnerService
{
    private readonly List<ScriptDefinition> _scripts = [];
    private readonly List<ScriptExecutionResult> _history = [];
    private readonly ILogger<ScriptRunnerService> _logger;
    private readonly SemaphoreSlim _executionLock = new(5, 5);

    public ScriptRunnerService(ILogger<ScriptRunnerService> logger)
    {
        _logger = logger;
        SeedExampleScripts();
    }

    public IReadOnlyList<ScriptDefinition> Scripts => _scripts.AsReadOnly();
    public IReadOnlyList<ScriptExecutionResult> ExecutionHistory => _history.AsReadOnly();

    public ScriptDefinition AddScript(ScriptDefinition script)
    {
        _scripts.Add(script);
        return script;
    }

    public void UpdateScript(ScriptDefinition script)
    {
        var index = _scripts.FindIndex(s => s.Id == script.Id);
        if (index >= 0)
        {
            script.UpdatedAt = DateTime.UtcNow;
            _scripts[index] = script;
        }
    }

    public void DeleteScript(Guid id) =>
        _scripts.RemoveAll(s => s.Id == id);

    public ScriptDefinition? GetScript(Guid id) =>
        _scripts.FirstOrDefault(s => s.Id == id);

    public async Task<ScriptExecutionResult> ExecuteScriptAsync(
        Guid scriptId,
        IProgress<OutputLine> progress,
        CancellationToken cancellationToken = default)
    {
        var script = GetScript(scriptId)
            ?? throw new ArgumentException($"Script {scriptId} not found");

        var result = new ScriptExecutionResult
        {
            ScriptId = scriptId,
            ScriptName = script.Name,
            Language = script.Language
        };
        _history.Insert(0, result);

        await _executionLock.WaitAsync(cancellationToken);
        var tempFile = Path.Combine(Path.GetTempPath(), $"automator_{result.ExecutionId}{script.Language.ToFileExtension()}");

        try
        {
            await File.WriteAllTextAsync(tempFile, script.Content, cancellationToken);

            var (executor, args) = GetExecutorInfo(script.Language);

            var psi = new ProcessStartInfo
            {
                FileName = executor,
                Arguments = $"{args} \"{tempFile}\"",
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                UseShellExecute = false,
                CreateNoWindow = true
            };

            using var process = Process.Start(psi)
                ?? throw new InvalidOperationException("Failed to start process");

            var outputTask = CaptureStreamAsync(process.StandardOutput, isError: false, result, progress, cancellationToken);
            var errorTask = CaptureStreamAsync(process.StandardError, isError: true, result, progress, cancellationToken);

            await process.WaitForExitAsync(cancellationToken);
            await Task.WhenAll(outputTask, errorTask);

            result.ExitCode = process.ExitCode;
            _logger.LogInformation("Script '{Name}' exited with code {Code}", script.Name, result.ExitCode);
        }
        catch (OperationCanceledException)
        {
            result.ExitCode = -1;
            ReportLine(new OutputLine { Text = "Execution cancelled by user.", IsError = true }, result, progress);
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Error executing script {ScriptId}", scriptId);
            result.ExitCode = -1;
            ReportLine(new OutputLine { Text = $"Execution error: {ex.Message}", IsError = true }, result, progress);
        }
        finally
        {
            result.CompletedAt = DateTime.UtcNow;
            _executionLock.Release();
            if (File.Exists(tempFile))
                File.Delete(tempFile);
        }

        return result;
    }

    private static void ReportLine(OutputLine line, ScriptExecutionResult result, IProgress<OutputLine> progress)
    {
        result.Output.Add(line);
        progress.Report(line);
    }

    private static async Task CaptureStreamAsync(
        StreamReader reader, bool isError,
        ScriptExecutionResult result, IProgress<OutputLine> progress,
        CancellationToken cancellationToken)
    {
        while (!reader.EndOfStream && !cancellationToken.IsCancellationRequested)
        {
            var text = await reader.ReadLineAsync(cancellationToken);
            if (text is null) break;
            ReportLine(new OutputLine { Text = text, IsError = isError }, result, progress);
        }
    }

    private static (string executor, string args) GetExecutorInfo(ScriptLanguage language)
    {
        var isWindows = RuntimeInformation.IsOSPlatform(OSPlatform.Windows);

        return language switch
        {
            ScriptLanguage.Bash when isWindows => ("wsl.exe", "bash"),
            ScriptLanguage.Bash => ("/bin/bash", ""),
            ScriptLanguage.PowerShell when isWindows => ("powershell.exe", "-NonInteractive -File"),
            ScriptLanguage.PowerShell => ("pwsh", "-NonInteractive -File"),
            ScriptLanguage.Python when isWindows => ("python.exe", ""),
            ScriptLanguage.Python => ("python3", ""),
            ScriptLanguage.Ansible => ("ansible-playbook", ""),
            _ => throw new NotSupportedException($"Language {language} is not supported")
        };
    }

    private void SeedExampleScripts()
    {
        _scripts.Add(new ScriptDefinition
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
        });

        _scripts.Add(new ScriptDefinition
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
        });

        _scripts.Add(new ScriptDefinition
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
        });

        _scripts.Add(new ScriptDefinition
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
        });

        _scripts.Add(new ScriptDefinition
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
        });
    }
}

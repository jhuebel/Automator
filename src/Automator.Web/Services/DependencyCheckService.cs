using System.ComponentModel;
using System.Diagnostics;
using System.Runtime.InteropServices;
using Automator.Web.Data;
using Automator.Web.Models;
using Microsoft.EntityFrameworkCore;

namespace Automator.Web.Services;

public class DependencyCheckService : IDependencyCheckService
{
    private readonly IDbContextFactory<AutomatorDbContext> _dbFactory;
    private readonly IConfiguration _config;

    private static readonly (string Name, string Description, string Command, string VersionArgs, ScriptLanguage Language)[] RuntimeChecks =
    [
        ("Bash",            "Bourne Again Shell",               "bash",             "--version",  ScriptLanguage.Bash),
        ("PowerShell Core", "Cross-platform PowerShell (pwsh)", "pwsh",             "--version",  ScriptLanguage.PowerShell),
        ("Python 3",        "Python interpreter",               "python3",          "--version",  ScriptLanguage.Python),
        ("Ansible",         "Ansible automation platform",      "ansible-playbook", "--version",  ScriptLanguage.Ansible),
        ("Terraform",       "Infrastructure as Code tool",      "terraform",        "version",    ScriptLanguage.Terraform),
    ];

    public DependencyCheckService(IDbContextFactory<AutomatorDbContext> dbFactory, IConfiguration config)
    {
        _dbFactory = dbFactory;
        _config = config;
    }

    public async Task<IReadOnlyList<DependencyCheckResult>> CheckRuntimesAsync(CancellationToken ct = default)
    {
        var tasks = RuntimeChecks.Select(r => CheckRuntimeAsync(r.Name, r.Description, r.Command, r.VersionArgs, r.Language, ct));
        return await Task.WhenAll(tasks);
    }

    public async Task<DatabaseStatus> GetDatabaseStatusAsync(CancellationToken ct = default)
    {
        var connectionString = _config.GetConnectionString("DefaultConnection") ?? "Data Source=automator.db";
        var filePath = connectionString
            .Split(';', StringSplitOptions.RemoveEmptyEntries)
            .Select(p => p.Trim())
            .FirstOrDefault(p => p.StartsWith("Data Source=", StringComparison.OrdinalIgnoreCase))
            ?.Substring("Data Source=".Length)
            ?? "automator.db";

        var fullPath = Path.IsPathRooted(filePath)
            ? filePath
            : Path.Combine(Directory.GetCurrentDirectory(), filePath);

        long? fileSize = File.Exists(fullPath) ? new FileInfo(fullPath).Length : null;

        try
        {
            await using var db = await _dbFactory.CreateDbContextAsync(ct);
            var scriptCount = await db.Scripts.CountAsync(ct);
            var jobCount = await db.ScheduledJobs.CountAsync(ct);
            var historyCount = await db.ExecutionHistory.CountAsync(ct);

            return new DatabaseStatus(fullPath, fileSize, true, scriptCount, jobCount, historyCount);
        }
        catch (Exception ex)
        {
            return new DatabaseStatus(fullPath, fileSize, false, 0, 0, 0, ex.Message);
        }
    }

    private static async Task<DependencyCheckResult> CheckRuntimeAsync(
        string name, string description, string command, string versionArgs,
        ScriptLanguage language, CancellationToken ct)
    {
        var resolvedCommand = RuntimeInformation.IsOSPlatform(OSPlatform.Windows) && language == ScriptLanguage.PowerShell
            ? "powershell.exe"
            : command;

        try
        {
            using var timeoutCts = CancellationTokenSource.CreateLinkedTokenSource(ct);
            timeoutCts.CancelAfter(TimeSpan.FromSeconds(5));

            var path = await ResolvePathAsync(resolvedCommand, timeoutCts.Token);

            var psi = new ProcessStartInfo
            {
                FileName = resolvedCommand,
                Arguments = versionArgs,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                UseShellExecute = false,
                CreateNoWindow = true
            };

            using var process = Process.Start(psi) ?? throw new InvalidOperationException("Process did not start");

            var stdoutTask = process.StandardOutput.ReadToEndAsync(timeoutCts.Token);
            var stderrTask = process.StandardError.ReadToEndAsync(timeoutCts.Token);
            await process.WaitForExitAsync(timeoutCts.Token);

            var output = await stdoutTask + await stderrTask;
            var version = output.Split('\n', StringSplitOptions.RemoveEmptyEntries).FirstOrDefault()?.Trim();

            return new DependencyCheckResult(name, description, true, version, path, null, language);
        }
        catch (OperationCanceledException)
        {
            return new DependencyCheckResult(name, description, false, null, null, "Timed out after 5s", language);
        }
        catch (Exception ex) when (ex is Win32Exception or FileNotFoundException or InvalidOperationException)
        {
            return new DependencyCheckResult(name, description, false, null, null, "Not found in PATH", language);
        }
        catch (Exception ex)
        {
            return new DependencyCheckResult(name, description, false, null, null, ex.Message, language);
        }
    }

    private static async Task<string?> ResolvePathAsync(string command, CancellationToken ct)
    {
        var whichCommand = RuntimeInformation.IsOSPlatform(OSPlatform.Windows) ? "where" : "which";
        try
        {
            var psi = new ProcessStartInfo
            {
                FileName = whichCommand,
                Arguments = command,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                UseShellExecute = false,
                CreateNoWindow = true
            };
            using var process = Process.Start(psi);
            if (process is null) return null;
            var output = await process.StandardOutput.ReadLineAsync(ct);
            await process.WaitForExitAsync(ct);
            return output?.Trim();
        }
        catch { return null; }
    }
}

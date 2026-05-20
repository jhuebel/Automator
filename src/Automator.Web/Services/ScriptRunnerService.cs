using System.Diagnostics;
using System.Runtime.InteropServices;
using Automator.Web.Data;
using Automator.Web.Models;
using Microsoft.EntityFrameworkCore;

namespace Automator.Web.Services;

public class ScriptRunnerService : IScriptRunnerService
{
    private readonly IDbContextFactory<AutomatorDbContext> _dbFactory;
    private readonly IAuditLogService _audit;
    private readonly ILogger<ScriptRunnerService> _logger;
    private readonly SemaphoreSlim _executionLock;

    public ScriptRunnerService(IDbContextFactory<AutomatorDbContext> dbFactory, IAuditLogService audit, ILogger<ScriptRunnerService> logger)
    {
        _dbFactory = dbFactory;
        _audit = audit;
        _logger = logger;
        using var db = dbFactory.CreateDbContext();
        var maxConcurrent = db.Settings.Find(1)?.MaxConcurrentExecutions ?? 5;
        _executionLock = new SemaphoreSlim(maxConcurrent, maxConcurrent);
    }

    public IReadOnlyList<ScriptDefinition> Scripts
    {
        get
        {
            using var db = _dbFactory.CreateDbContext();
            return db.Scripts.OrderBy(s => s.Name).ToList();
        }
    }

    public IReadOnlyList<ScriptExecutionResult> ExecutionHistory
    {
        get
        {
            using var db = _dbFactory.CreateDbContext();
            var limit = db.Settings.Find(1)?.MaxHistoryRecords ?? 1000;
            return db.ExecutionHistory.OrderByDescending(r => r.StartedAt).Take(limit).ToList();
        }
    }

    public ScriptDefinition AddScript(ScriptDefinition script)
    {
        using var db = _dbFactory.CreateDbContext();
        db.Scripts.Add(script);
        db.SaveChanges();
        return script;
    }

    public void UpdateScript(ScriptDefinition script)
    {
        script.UpdatedAt = DateTime.UtcNow;
        using var db = _dbFactory.CreateDbContext();
        db.Scripts.Update(script);
        db.SaveChanges();
    }

    public void DeleteScript(Guid id)
    {
        using var db = _dbFactory.CreateDbContext();
        db.Scripts.Where(s => s.Id == id).ExecuteDelete();
    }

    public ScriptDefinition? GetScript(Guid id)
    {
        using var db = _dbFactory.CreateDbContext();
        return db.Scripts.Find(id);
    }

    public async Task<ScriptExecutionResult> ExecuteScriptAsync(
        Guid scriptId,
        IProgress<OutputLine> progress,
        CancellationToken cancellationToken = default,
        string? username = null,
        Dictionary<string, string>? variables = null)
    {
        ScriptDefinition script;
        AppSetting settings;
        using (var db = _dbFactory.CreateDbContext())
        {
            script = await db.Scripts.FindAsync([scriptId], CancellationToken.None)
                ?? throw new ArgumentException($"Script {scriptId} not found");
            settings = await db.Settings.FindAsync([1], CancellationToken.None) ?? new AppSetting();
        }

        var result = new ScriptExecutionResult
        {
            ScriptId = scriptId,
            ScriptName = script.Name,
            Language = script.Language
        };

        using (var db = _dbFactory.CreateDbContext())
        {
            db.ExecutionHistory.Add(result);
            await db.SaveChangesAsync(CancellationToken.None);
        }

        await _audit.LogAsync("Script.Executed", script.Name, "started", username);
        await _executionLock.WaitAsync(cancellationToken);
        using var timeoutCts = new CancellationTokenSource(TimeSpan.FromSeconds(settings.ExecutionTimeoutSeconds));
        using var linkedCts = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken, timeoutCts.Token);
        var execToken = linkedCts.Token;

        var tempFile = Path.Combine(Path.GetTempPath(), $"automator_{result.ExecutionId}{script.Language.ToFileExtension()}");

        try
        {
            await File.WriteAllTextAsync(tempFile, script.Content, execToken);

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

            if (variables is not null)
                foreach (var (key, value) in variables)
                    if (!string.IsNullOrWhiteSpace(key))
                        psi.Environment[key] = value;

            using var process = Process.Start(psi)
                ?? throw new InvalidOperationException("Failed to start process");

            var outputTask = CaptureStreamAsync(process.StandardOutput, isError: false, result, progress, execToken);
            var errorTask = CaptureStreamAsync(process.StandardError, isError: true, result, progress, execToken);

            await process.WaitForExitAsync(execToken);
            await Task.WhenAll(outputTask, errorTask);

            result.ExitCode = process.ExitCode;
            _logger.LogInformation("Script '{Name}' exited with code {Code}", script.Name, result.ExitCode);
        }
        catch (OperationCanceledException)
        {
            result.ExitCode = -1;
            var msg = timeoutCts.IsCancellationRequested
                ? $"Execution timed out after {settings.ExecutionTimeoutSeconds} seconds."
                : "Execution cancelled by user.";
            ReportLine(new OutputLine { Text = msg, IsError = true }, result, progress);
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

            var outcome = result.IsSuccess ? $"exit {result.ExitCode}" : $"failed (exit {result.ExitCode})";
            await _audit.LogAsync("Script.Executed", script.Name, outcome, username);

            using var db = _dbFactory.CreateDbContext();
            db.ExecutionHistory.Update(result);
            await db.SaveChangesAsync(CancellationToken.None);

            if (File.Exists(tempFile)) File.Delete(tempFile);
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
}

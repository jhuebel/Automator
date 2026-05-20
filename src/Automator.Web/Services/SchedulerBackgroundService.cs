using Automator.Web.Models;

namespace Automator.Web.Services;

public class SchedulerBackgroundService : BackgroundService
{
    private readonly IJobSchedulerService _scheduler;
    private readonly IScriptRunnerService _scriptRunner;
    private readonly ILogger<SchedulerBackgroundService> _logger;
    private readonly HashSet<Guid> _runningJobs = [];
    private readonly SemaphoreSlim _lock = new(1, 1);

    private static readonly TimeSpan TickInterval = TimeSpan.FromSeconds(15);

    public SchedulerBackgroundService(
        IJobSchedulerService scheduler,
        IScriptRunnerService scriptRunner,
        ILogger<SchedulerBackgroundService> logger)
    {
        _scheduler = scheduler;
        _scriptRunner = scriptRunner;
        _logger = logger;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation("Job scheduler started, tick interval: {Interval}s", TickInterval.TotalSeconds);

        while (!stoppingToken.IsCancellationRequested)
        {
            await Task.Delay(TickInterval, stoppingToken).ContinueWith(_ => { });

            if (stoppingToken.IsCancellationRequested) break;

            var due = _scheduler.GetDueJobs(DateTime.UtcNow);
            foreach (var job in due)
            {
                await _lock.WaitAsync(stoppingToken);
                bool alreadyRunning = _runningJobs.Contains(job.Id);
                if (!alreadyRunning) _runningJobs.Add(job.Id);
                _lock.Release();

                if (alreadyRunning)
                {
                    _logger.LogWarning("Skipping job '{Name}' — previous execution still in progress", job.Name);
                    continue;
                }

                _ = Task.Run(() => RunJobAsync(job, stoppingToken), stoppingToken);
            }
        }

        _logger.LogInformation("Job scheduler stopped");
    }

    private async Task RunJobAsync(ScheduledJob job, CancellationToken stoppingToken)
    {
        _logger.LogInformation("Firing scheduled job '{Name}' (script {ScriptId})", job.Name, job.ScriptId);
        try
        {
            var script = _scriptRunner.GetScript(job.ScriptId);
            var defaults = script?.Variables
                .Where(v => !string.IsNullOrWhiteSpace(v.Name))
                .ToDictionary(v => v.Name, v => v.DefaultValue);

            var result = await _scriptRunner.ExecuteScriptAsync(
                job.ScriptId,
                new Progress<OutputLine>(),
                stoppingToken,
                variables: defaults);

            _scheduler.RecordExecution(job.Id, result.ExitCode ?? -1);
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Unhandled error in scheduled job '{Name}'", job.Name);
            _scheduler.RecordExecution(job.Id, -1);
        }
        finally
        {
            await _lock.WaitAsync(CancellationToken.None);
            _runningJobs.Remove(job.Id);
            _lock.Release();
        }
    }
}

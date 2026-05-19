using Automator.Web.Models;
using Cronos;

namespace Automator.Web.Services;

public class JobSchedulerService : IJobSchedulerService
{
    private readonly List<ScheduledJob> _jobs = [];
    private readonly ILogger<JobSchedulerService> _logger;

    public JobSchedulerService(ILogger<JobSchedulerService> logger, IScriptRunnerService scriptService)
    {
        _logger = logger;
        SeedExampleJobs(scriptService);
    }

    public IReadOnlyList<ScheduledJob> Jobs => _jobs.AsReadOnly();

    public ScheduledJob AddJob(ScheduledJob job)
    {
        job.NextRunAt = GetNextOccurrence(job.CronExpression);
        _jobs.Add(job);
        return job;
    }

    public void UpdateJob(ScheduledJob job)
    {
        var index = _jobs.FindIndex(j => j.Id == job.Id);
        if (index < 0) return;
        job.NextRunAt = GetNextOccurrence(job.CronExpression);
        _jobs[index] = job;
    }

    public void DeleteJob(Guid id) => _jobs.RemoveAll(j => j.Id == id);

    public ScheduledJob? GetJob(Guid id) => _jobs.FirstOrDefault(j => j.Id == id);

    public IReadOnlyList<ScheduledJob> GetDueJobs(DateTime utcNow) =>
        _jobs.Where(j => j.IsEnabled && j.NextRunAt.HasValue && j.NextRunAt.Value <= utcNow).ToList();

    public void RecordExecution(Guid jobId, int exitCode)
    {
        var job = _jobs.FirstOrDefault(j => j.Id == jobId);
        if (job is null) return;
        job.LastRunAt = DateTime.UtcNow;
        job.LastExitCode = exitCode;
        job.NextRunAt = GetNextOccurrence(job.CronExpression);
        _logger.LogInformation("Job '{Name}' completed with exit code {Code}, next run: {Next}",
            job.Name, exitCode, job.NextRunAt?.ToString("u") ?? "none");
    }

    public DateTime? GetNextOccurrence(string cronExpression)
    {
        if (string.IsNullOrWhiteSpace(cronExpression)) return null;
        try
        {
            var schedule = CronExpression.Parse(cronExpression, CronFormat.Standard);
            return schedule.GetNextOccurrence(DateTime.UtcNow, TimeZoneInfo.Utc);
        }
        catch
        {
            return null;
        }
    }

    private void SeedExampleJobs(IScriptRunnerService scriptService)
    {
        var sysInfoBash = scriptService.Scripts.FirstOrDefault(s => s.Name == "System Info (Bash)");
        var diskCheck = scriptService.Scripts.FirstOrDefault(s => s.Name == "Disk Space Alert (PowerShell)");

        if (sysInfoBash is not null)
        {
            var job = new ScheduledJob
            {
                Name = "Hourly System Info",
                ScriptId = sysInfoBash.Id,
                CronExpression = "0 * * * *",
                IsEnabled = false
            };
            job.NextRunAt = GetNextOccurrence(job.CronExpression);
            _jobs.Add(job);
        }

        if (diskCheck is not null)
        {
            var job = new ScheduledJob
            {
                Name = "Daily Disk Check",
                ScriptId = diskCheck.Id,
                CronExpression = "0 8 * * *",
                IsEnabled = false
            };
            job.NextRunAt = GetNextOccurrence(job.CronExpression);
            _jobs.Add(job);
        }
    }
}

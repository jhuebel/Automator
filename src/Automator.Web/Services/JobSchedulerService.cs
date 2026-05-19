using Automator.Web.Data;
using Automator.Web.Models;
using Cronos;
using Microsoft.EntityFrameworkCore;

namespace Automator.Web.Services;

public class JobSchedulerService : IJobSchedulerService
{
    private readonly IDbContextFactory<AutomatorDbContext> _dbFactory;
    private readonly ILogger<JobSchedulerService> _logger;

    public JobSchedulerService(IDbContextFactory<AutomatorDbContext> dbFactory, ILogger<JobSchedulerService> logger)
    {
        _dbFactory = dbFactory;
        _logger = logger;
    }

    public IReadOnlyList<ScheduledJob> Jobs
    {
        get
        {
            using var db = _dbFactory.CreateDbContext();
            return db.ScheduledJobs.OrderBy(j => j.Name).ToList();
        }
    }

    public ScheduledJob AddJob(ScheduledJob job)
    {
        job.NextRunAt = GetNextOccurrence(job.CronExpression);
        using var db = _dbFactory.CreateDbContext();
        db.ScheduledJobs.Add(job);
        db.SaveChanges();
        return job;
    }

    public void UpdateJob(ScheduledJob job)
    {
        job.NextRunAt = GetNextOccurrence(job.CronExpression);
        using var db = _dbFactory.CreateDbContext();
        db.ScheduledJobs.Update(job);
        db.SaveChanges();
    }

    public void DeleteJob(Guid id)
    {
        using var db = _dbFactory.CreateDbContext();
        db.ScheduledJobs.Where(j => j.Id == id).ExecuteDelete();
    }

    public ScheduledJob? GetJob(Guid id)
    {
        using var db = _dbFactory.CreateDbContext();
        return db.ScheduledJobs.Find(id);
    }

    public IReadOnlyList<ScheduledJob> GetDueJobs(DateTime utcNow)
    {
        using var db = _dbFactory.CreateDbContext();
        return db.ScheduledJobs
            .Where(j => j.IsEnabled && j.NextRunAt != null && j.NextRunAt <= utcNow)
            .ToList();
    }

    public void RecordExecution(Guid jobId, int exitCode)
    {
        using var db = _dbFactory.CreateDbContext();
        var job = db.ScheduledJobs.Find(jobId);
        if (job is null) return;
        job.LastRunAt = DateTime.UtcNow;
        job.LastExitCode = exitCode;
        job.NextRunAt = GetNextOccurrence(job.CronExpression);
        db.SaveChanges();
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
        catch { return null; }
    }
}

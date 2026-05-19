using Automator.Web.Models;

namespace Automator.Web.Services;

public interface IJobSchedulerService
{
    IReadOnlyList<ScheduledJob> Jobs { get; }

    ScheduledJob AddJob(ScheduledJob job);
    void UpdateJob(ScheduledJob job);
    void DeleteJob(Guid id);
    ScheduledJob? GetJob(Guid id);

    IReadOnlyList<ScheduledJob> GetDueJobs(DateTime utcNow);
    void RecordExecution(Guid jobId, int exitCode);
    DateTime? GetNextOccurrence(string cronExpression);
}

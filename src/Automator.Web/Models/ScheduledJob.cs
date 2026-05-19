namespace Automator.Web.Models;

public class ScheduledJob
{
    public Guid Id { get; set; } = Guid.NewGuid();
    public string Name { get; set; } = string.Empty;
    public Guid ScriptId { get; set; }
    public string CronExpression { get; set; } = string.Empty;
    public bool IsEnabled { get; set; } = true;
    public DateTime? LastRunAt { get; set; }
    public DateTime? NextRunAt { get; set; }
    public int? LastExitCode { get; set; }
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

    public bool LastRunSucceeded => LastExitCode == 0;
}

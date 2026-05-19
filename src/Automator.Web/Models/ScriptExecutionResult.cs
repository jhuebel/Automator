namespace Automator.Web.Models;

public class ScriptExecutionResult
{
    public Guid ExecutionId { get; set; } = Guid.NewGuid();
    public Guid ScriptId { get; set; }
    public string ScriptName { get; set; } = string.Empty;
    public ScriptLanguage Language { get; set; }
    public DateTime StartedAt { get; set; } = DateTime.UtcNow;
    public DateTime? CompletedAt { get; set; }
    public int? ExitCode { get; set; }
    public List<OutputLine> Output { get; set; } = [];

    public bool IsRunning => CompletedAt is null;
    public bool IsSuccess => ExitCode == 0;
    public TimeSpan? Duration => CompletedAt.HasValue ? CompletedAt.Value - StartedAt : null;
}

public class OutputLine
{
    public string Text { get; set; } = string.Empty;
    public bool IsError { get; set; }
    public DateTime Timestamp { get; set; } = DateTime.UtcNow;
}

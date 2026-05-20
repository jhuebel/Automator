namespace Automator.Web.Models;

public class AppSetting
{
    public int Id { get; set; } = 1;
    public int ExecutionTimeoutSeconds { get; set; } = 300;
    public int MaxConcurrentExecutions { get; set; } = 5;
    public int MaxHistoryRecords { get; set; } = 1000;
    public string? AnthropicApiKey { get; set; }
    public string AnthropicModel { get; set; } = "claude-sonnet-4-6";
}

using Automator.Web.Models;

namespace Automator.Web.Services;

public interface IScriptRunnerService
{
    IReadOnlyList<ScriptDefinition> Scripts { get; }
    IReadOnlyList<ScriptExecutionResult> ExecutionHistory { get; }

    ScriptDefinition AddScript(ScriptDefinition script);
    void UpdateScript(ScriptDefinition script);
    void DeleteScript(Guid id);
    ScriptDefinition? GetScript(Guid id);

    Task<ScriptExecutionResult> ExecuteScriptAsync(
        Guid scriptId,
        IProgress<OutputLine> progress,
        CancellationToken cancellationToken = default,
        string? username = null);
}

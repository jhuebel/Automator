namespace Automator.Web.Models;

public record DependencyCheckResult(
    string Name,
    string Description,
    bool IsAvailable,
    string? Version,
    string? Path,
    string? ErrorMessage,
    ScriptLanguage? Language = null
);

public record DatabaseStatus(
    string FilePath,
    long? FileSizeBytes,
    bool IsAccessible,
    int ScriptCount,
    int JobCount,
    int HistoryCount,
    string? ErrorMessage = null
);

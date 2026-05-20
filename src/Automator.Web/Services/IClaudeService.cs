namespace Automator.Web.Services;

public interface IClaudeService
{
    bool IsConfigured { get; }
    IAsyncEnumerable<string> StreamAsync(string system, string user, CancellationToken ct = default);
}

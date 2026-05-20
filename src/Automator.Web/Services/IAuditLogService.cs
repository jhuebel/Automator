using Automator.Web.Models;

namespace Automator.Web.Services;

public interface IAuditLogService
{
    Task LogAsync(string action, string? resource = null, string? details = null, string? username = null);
    IReadOnlyList<AuditLog> GetRecentLogs(int count = 200);
}

using Automator.Web.Data;
using Automator.Web.Models;
using Microsoft.EntityFrameworkCore;

namespace Automator.Web.Services;

public class AuditLogService : IAuditLogService
{
    private readonly IDbContextFactory<AutomatorDbContext> _dbFactory;

    public AuditLogService(IDbContextFactory<AutomatorDbContext> dbFactory)
    {
        _dbFactory = dbFactory;
    }

    public async Task LogAsync(string action, string? resource = null, string? details = null, string? username = null)
    {
        try
        {
            using var db = _dbFactory.CreateDbContext();
            db.AuditLogs.Add(new AuditLog
            {
                Action = action,
                Resource = resource,
                Details = details,
                Username = username,
                Timestamp = DateTime.UtcNow
            });
            await db.SaveChangesAsync();
        }
        catch
        {
            // Audit logging must never crash the calling operation
        }
    }

    public IReadOnlyList<AuditLog> GetRecentLogs(int count = 200)
    {
        using var db = _dbFactory.CreateDbContext();
        return db.AuditLogs
            .OrderByDescending(l => l.Timestamp)
            .Take(count)
            .ToList();
    }
}

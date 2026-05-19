using Automator.Web.Models;

namespace Automator.Web.Services;

public interface IDependencyCheckService
{
    Task<IReadOnlyList<DependencyCheckResult>> CheckRuntimesAsync(CancellationToken ct = default);
    Task<DatabaseStatus> GetDatabaseStatusAsync(CancellationToken ct = default);
}

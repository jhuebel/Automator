using System.Text.Json;
using Automator.Web.Models;
using Microsoft.AspNetCore.Identity.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore;

namespace Automator.Web.Data;

public class AutomatorDbContext : IdentityDbContext<ApplicationUser>
{
    private static readonly JsonSerializerOptions JsonOptions = new();

    public AutomatorDbContext(DbContextOptions<AutomatorDbContext> options) : base(options) { }

    public DbSet<ScriptDefinition> Scripts => Set<ScriptDefinition>();
    public DbSet<ScriptExecutionResult> ExecutionHistory => Set<ScriptExecutionResult>();
    public DbSet<ScheduledJob> ScheduledJobs => Set<ScheduledJob>();
    public DbSet<AppSetting> Settings => Set<AppSetting>();

    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        base.OnModelCreating(modelBuilder);

        modelBuilder.Entity<ScriptExecutionResult>()
            .HasKey(r => r.ExecutionId);

        modelBuilder.Entity<ScriptDefinition>()
            .Property(s => s.Tags)
            .HasConversion(
                v => JsonSerializer.Serialize(v, JsonOptions),
                v => JsonSerializer.Deserialize<List<string>>(v, JsonOptions) ?? new List<string>());

        modelBuilder.Entity<ScriptExecutionResult>()
            .Property(r => r.Output)
            .HasConversion(
                v => JsonSerializer.Serialize(v, JsonOptions),
                v => JsonSerializer.Deserialize<List<OutputLine>>(v, JsonOptions) ?? new List<OutputLine>());
    }
}

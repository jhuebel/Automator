using Automator.Web.Components;
using Automator.Web.Data;
using Automator.Web.Models;
using Automator.Web.Services;
using Microsoft.EntityFrameworkCore;

var builder = WebApplication.CreateBuilder(args);

builder.Services.AddRazorComponents()
    .AddInteractiveServerComponents();

builder.Services.AddDbContextFactory<AutomatorDbContext>(options =>
    options.UseSqlite(builder.Configuration.GetConnectionString("DefaultConnection")));

builder.Services.AddSingleton<IScriptRunnerService, ScriptRunnerService>();
builder.Services.AddSingleton<IJobSchedulerService, JobSchedulerService>();
builder.Services.AddHostedService<SchedulerBackgroundService>();

var app = builder.Build();

// Ensure DB exists, clean up orphans, seed if empty
using (var db = app.Services.GetRequiredService<IDbContextFactory<AutomatorDbContext>>().CreateDbContext())
{
    db.Database.EnsureCreated();

    var orphaned = db.ExecutionHistory.Where(r => r.CompletedAt == null).ToList();
    foreach (var r in orphaned)
    {
        r.CompletedAt = DateTime.UtcNow;
        r.ExitCode = -1;
        r.Output.Add(new OutputLine { Text = "Interrupted: application was restarted.", IsError = true });
    }
    if (orphaned.Count > 0) db.SaveChanges();

    if (!db.Scripts.Any())
        DataSeeder.Seed(db);
}

if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Error");
    app.UseHsts();
}

app.UseStaticFiles();
app.UseAntiforgery();

app.MapRazorComponents<App>()
    .AddInteractiveServerRenderMode();

app.Run();

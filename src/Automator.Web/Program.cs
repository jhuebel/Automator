using Automator.Web.Components;
using Automator.Web.Data;
using Automator.Web.Models;
using Automator.Web.Services;
using Microsoft.AspNetCore.Identity;
using Microsoft.EntityFrameworkCore;

var builder = WebApplication.CreateBuilder(args);

builder.Services.AddRazorComponents()
    .AddInteractiveServerComponents();

builder.Services.AddRazorPages();

builder.Services.AddDbContextFactory<AutomatorDbContext>(options =>
    options.UseSqlite(builder.Configuration.GetConnectionString("DefaultConnection")));

builder.Services.AddIdentity<ApplicationUser, IdentityRole>(options =>
    {
        options.Password.RequireDigit = true;
        options.Password.RequiredLength = 8;
        options.Password.RequireUppercase = false;
        options.Password.RequireNonAlphanumeric = false;
        options.Lockout.DefaultLockoutTimeSpan = TimeSpan.FromMinutes(5);
        options.Lockout.MaxFailedAccessAttempts = 5;
    })
    .AddEntityFrameworkStores<AutomatorDbContext>()
    .AddDefaultTokenProviders();

builder.Services.ConfigureApplicationCookie(options =>
{
    options.LoginPath = "/account/login";
    options.LogoutPath = "/account/logout";
    options.AccessDeniedPath = "/account/login";
    options.ExpireTimeSpan = TimeSpan.FromDays(14);
    options.SlidingExpiration = true;
});

builder.Services.AddCascadingAuthenticationState();

builder.Services.AddTransient<IDependencyCheckService, DependencyCheckService>();
builder.Services.AddSingleton<IScriptRunnerService, ScriptRunnerService>();
builder.Services.AddSingleton<IJobSchedulerService, JobSchedulerService>();
builder.Services.AddHostedService<SchedulerBackgroundService>();

var app = builder.Build();

// Ensure DB schema exists (handles schema upgrade from pre-Identity builds)
using (var scope = app.Services.CreateScope())
{
    var factory = scope.ServiceProvider.GetRequiredService<IDbContextFactory<AutomatorDbContext>>();
    using var db = factory.CreateDbContext();

    try
    {
        // Probe for Identity tables — if missing, schema needs to be rebuilt
        db.Users.FirstOrDefault();
    }
    catch (Microsoft.Data.Sqlite.SqliteException)
    {
        db.Database.EnsureDeleted();
    }

    db.Database.EnsureCreated();

    // Add Settings table for existing databases that predate this schema change
    db.Database.ExecuteSqlRaw("""
        CREATE TABLE IF NOT EXISTS "Settings" (
            "Id"                       INTEGER NOT NULL CONSTRAINT "PK_Settings" PRIMARY KEY,
            "ExecutionTimeoutSeconds"  INTEGER NOT NULL DEFAULT 300,
            "MaxConcurrentExecutions"  INTEGER NOT NULL DEFAULT 5,
            "MaxHistoryRecords"        INTEGER NOT NULL DEFAULT 1000
        )
        """);

    var orphaned = db.ExecutionHistory.Where(r => r.CompletedAt == null).ToList();
    foreach (var r in orphaned)
    {
        r.CompletedAt = DateTime.UtcNow;
        r.ExitCode = -1;
        r.Output.Add(new OutputLine { Text = "Interrupted: application was restarted.", IsError = true });
    }
    if (orphaned.Count > 0) db.SaveChanges();

    // Seed roles
    var roleManager = scope.ServiceProvider.GetRequiredService<RoleManager<IdentityRole>>();
    foreach (var role in new[] { "Admin", "Operator", "Viewer" })
    {
        if (!await roleManager.RoleExistsAsync(role))
            await roleManager.CreateAsync(new IdentityRole(role));
    }

    // Seed default admin
    var userManager = scope.ServiceProvider.GetRequiredService<UserManager<ApplicationUser>>();
    var adminConfig = app.Configuration.GetSection("DefaultAdmin");
    var adminUsername = adminConfig["Username"] ?? "admin";
    var adminEmail = adminConfig["Email"] ?? "admin@localhost";
    var adminPassword = adminConfig["Password"] ?? "Admin1234!";

    if (await userManager.FindByNameAsync(adminUsername) is null)
    {
        var adminUser = new ApplicationUser
        {
            UserName = adminUsername,
            Email = adminEmail,
            EmailConfirmed = true
        };
        var result = await userManager.CreateAsync(adminUser, adminPassword);
        if (result.Succeeded)
            await userManager.AddToRoleAsync(adminUser, "Admin");
    }

    // Seed default app settings
    if (!db.Settings.Any())
    {
        db.Settings.Add(new AppSetting());
        db.SaveChanges();
    }

    // Seed example scripts and jobs if empty
    if (!db.Scripts.Any())
        DataSeeder.Seed(db);
}

if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Error");
    app.UseHsts();
}

app.UseStaticFiles();
app.UseAuthentication();
app.UseAuthorization();
app.UseAntiforgery();

app.MapRazorPages();

app.MapGet("/account/logout", async (SignInManager<ApplicationUser> signInManager) =>
{
    await signInManager.SignOutAsync();
    return Results.Redirect("/account/login");
});

app.MapRazorComponents<App>()
    .AddInteractiveServerRenderMode();

app.Run();

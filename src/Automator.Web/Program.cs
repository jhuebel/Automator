using Automator.Web.Components;
using Automator.Web.Data;
using Automator.Web.Models;
using Automator.Web.Services;
using Microsoft.AspNetCore.Identity;
using Microsoft.EntityFrameworkCore;
using MudBlazor.Services;

var builder = WebApplication.CreateBuilder(args);
builder.WebHost.UseStaticWebAssets();

var dbProvider = (builder.Configuration["DatabaseProvider"] ?? "Sqlite").Trim();
var connectionString = builder.Configuration.GetConnectionString("DefaultConnection") ?? "Data Source=automator.db";
var isMySql = dbProvider.Equals("MySQL", StringComparison.OrdinalIgnoreCase)
           || dbProvider.Equals("MariaDB", StringComparison.OrdinalIgnoreCase);

// Detect MySQL/MariaDB server version once at startup (requires a brief connection)
ServerVersion? mySqlVersion = isMySql ? ServerVersion.AutoDetect(connectionString) : null;

builder.Services.AddRazorComponents()
    .AddInteractiveServerComponents();

builder.Services.Configure<Microsoft.AspNetCore.Components.Server.CircuitOptions>(o =>
    o.DetailedErrors = true);

builder.Services.AddRazorPages();

builder.Services.AddDbContextFactory<AutomatorDbContext>(options =>
{
    if (isMySql)
        options.UseMySql(connectionString, mySqlVersion!);
    else
        options.UseSqlite(connectionString);
});

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
builder.Services.AddSingleton<IAuditLogService, AuditLogService>();
builder.Services.AddSingleton<IScriptRunnerService, ScriptRunnerService>();
builder.Services.AddSingleton<IJobSchedulerService, JobSchedulerService>();
builder.Services.AddHostedService<SchedulerBackgroundService>();
builder.Services.AddHttpClient<ClaudeService>();
builder.Services.AddScoped<IClaudeService, ClaudeService>();
builder.Services.AddScoped<HelpDrawerState>();
builder.Services.AddMudServices();

var app = builder.Build();

// Ensure DB schema exists (handles schema upgrade from pre-Identity builds)
using (var scope = app.Services.CreateScope())
{
    var factory = scope.ServiceProvider.GetRequiredService<IDbContextFactory<AutomatorDbContext>>();
    using var db = factory.CreateDbContext();

    if (!isMySql)
    {
        // SQLite: probe for Identity tables; if missing the schema needs to be rebuilt
        try { db.Users.FirstOrDefault(); }
        catch (Microsoft.Data.Sqlite.SqliteException) { db.Database.EnsureDeleted(); }
    }

    db.Database.EnsureCreated();

    if (!isMySql)
    {
        // SQLite: ensure tables added after initial release exist in older databases
        db.Database.ExecuteSqlRaw("""
            CREATE TABLE IF NOT EXISTS "Settings" (
                "Id"                       INTEGER NOT NULL CONSTRAINT "PK_Settings" PRIMARY KEY,
                "ExecutionTimeoutSeconds"  INTEGER NOT NULL DEFAULT 300,
                "MaxConcurrentExecutions"  INTEGER NOT NULL DEFAULT 5,
                "MaxHistoryRecords"        INTEGER NOT NULL DEFAULT 1000
            )
            """);

        db.Database.ExecuteSqlRaw("""
            CREATE TABLE IF NOT EXISTS "AuditLogs" (
                "Id"        INTEGER NOT NULL CONSTRAINT "PK_AuditLogs" PRIMARY KEY AUTOINCREMENT,
                "Timestamp" TEXT    NOT NULL,
                "Username"  TEXT    NULL,
                "Action"    TEXT    NOT NULL,
                "Resource"  TEXT    NULL,
                "Details"   TEXT    NULL
            )
            """);

        // EnsureCreated won't ALTER existing tables — add missing columns manually
        var settingsCols = db.Database
            .SqlQueryRaw<string>("SELECT name FROM pragma_table_info('Settings')")
            .ToList();
        if (!settingsCols.Contains("AnthropicApiKey"))
            db.Database.ExecuteSqlRaw("ALTER TABLE Settings ADD COLUMN AnthropicApiKey TEXT NULL");
        if (!settingsCols.Contains("AnthropicModel"))
            db.Database.ExecuteSqlRaw(
                "ALTER TABLE Settings ADD COLUMN AnthropicModel TEXT NOT NULL DEFAULT 'claude-sonnet-5'");
        if (!settingsCols.Contains("AnthropicEffort"))
            db.Database.ExecuteSqlRaw(
                "ALTER TABLE Settings ADD COLUMN AnthropicEffort TEXT NOT NULL DEFAULT 'high'");

        var scriptCols = db.Database
            .SqlQueryRaw<string>("SELECT name FROM pragma_table_info('Scripts')")
            .ToList();
        if (!scriptCols.Contains("Variables"))
            db.Database.ExecuteSqlRaw("ALTER TABLE Scripts ADD COLUMN Variables TEXT NOT NULL DEFAULT '[]'");
    }
    else
    {
        // MySQL/MariaDB: EnsureCreated handles table creation; add missing columns for older databases
        var settingsCols = db.Database
            .SqlQueryRaw<string>(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Settings'")
            .ToList();
        if (!settingsCols.Any(c => c.Equals("AnthropicApiKey", StringComparison.OrdinalIgnoreCase)))
            db.Database.ExecuteSqlRaw("ALTER TABLE `Settings` ADD COLUMN `AnthropicApiKey` TEXT NULL");
        if (!settingsCols.Any(c => c.Equals("AnthropicModel", StringComparison.OrdinalIgnoreCase)))
            db.Database.ExecuteSqlRaw(
                "ALTER TABLE `Settings` ADD COLUMN `AnthropicModel` TEXT NOT NULL DEFAULT 'claude-sonnet-5'");
        if (!settingsCols.Any(c => c.Equals("AnthropicEffort", StringComparison.OrdinalIgnoreCase)))
            db.Database.ExecuteSqlRaw(
                "ALTER TABLE `Settings` ADD COLUMN `AnthropicEffort` TEXT NOT NULL DEFAULT 'high'");

        var scriptCols = db.Database
            .SqlQueryRaw<string>(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Scripts'")
            .ToList();
        if (!scriptCols.Any(c => c.Equals("Variables", StringComparison.OrdinalIgnoreCase)))
            db.Database.ExecuteSqlRaw("ALTER TABLE `Scripts` ADD COLUMN `Variables` LONGTEXT NOT NULL DEFAULT ('[]')");
    }

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
    foreach (var role in new[] { "Admin", "Developer", "Operator", "Viewer" })
    {
        if (!await roleManager.RoleExistsAsync(role))
            await roleManager.CreateAsync(new IdentityRole(role));
    }

    // Seed default users
    var userManager = scope.ServiceProvider.GetRequiredService<UserManager<ApplicationUser>>();

    async Task SeedUserAsync(string section, string defaultUsername, string defaultEmail, string defaultPassword, string role)
    {
        var cfg = app.Configuration.GetSection(section);
        var username = cfg["Username"] ?? defaultUsername;
        var email = cfg["Email"] ?? defaultEmail;
        var password = cfg["Password"] ?? defaultPassword;
        if (await userManager.FindByNameAsync(username) is null)
        {
            var user = new ApplicationUser { UserName = username, Email = email, EmailConfirmed = true };
            var result = await userManager.CreateAsync(user, password);
            if (result.Succeeded)
                await userManager.AddToRoleAsync(user, role);
        }
    }

    await SeedUserAsync("DefaultAdmin",    "admin",    "admin@localhost",    "Admin1234!",    "Admin");
    await SeedUserAsync("DefaultOperator", "operator", "operator@localhost", "Operator1234!", "Operator");
    await SeedUserAsync("DefaultViewer",   "viewer",   "viewer@localhost",   "Viewer1234!",   "Viewer");

    // Seed default app settings
    if (!db.Settings.Any())
    {
        db.Settings.Add(new AppSetting());
        db.SaveChanges();
    }
    else
    {
        // Retired model strings from earlier releases aren't in the current model
        // picker; roll existing installs forward to the current default.
        var retiredModels = new[] { "claude-sonnet-4-6", "claude-opus-4-7", "claude-sonnet-4-5", "claude-opus-4-6" };
        var existingSettings = db.Settings.Find(1);
        if (existingSettings is not null && retiredModels.Contains(existingSettings.AnthropicModel))
        {
            existingSettings.AnthropicModel = "claude-sonnet-5";
            db.SaveChanges();
        }
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

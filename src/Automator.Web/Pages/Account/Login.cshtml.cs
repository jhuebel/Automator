using Automator.Web.Models;
using Automator.Web.Services;
using Microsoft.AspNetCore.Identity;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.RazorPages;

namespace Automator.Web.Pages.Account;

public class LoginModel : PageModel
{
    private readonly SignInManager<ApplicationUser> _signInManager;
    private readonly IAuditLogService _audit;

    public LoginModel(SignInManager<ApplicationUser> signInManager, IAuditLogService audit)
    {
        _signInManager = signInManager;
        _audit = audit;
    }

    [BindProperty] public string Username { get; set; } = string.Empty;
    [BindProperty] public string Password { get; set; } = string.Empty;
    [BindProperty] public bool RememberMe { get; set; }

    public string? ReturnUrl { get; set; }
    public string? ErrorMessage { get; set; }

    public void OnGet(string? returnUrl = null) => ReturnUrl = returnUrl;

    public async Task<IActionResult> OnPostAsync(string? returnUrl = null)
    {
        var result = await _signInManager.PasswordSignInAsync(
            Username, Password, RememberMe, lockoutOnFailure: true);

        if (result.Succeeded)
        {
            await _audit.LogAsync("Login", Username, "success", Username);
            return LocalRedirect(returnUrl ?? "/");
        }

        var reason = result.IsLockedOut ? "locked out" : "invalid credentials";
        await _audit.LogAsync("Login.Failed", Username, reason, Username);

        ErrorMessage = result.IsLockedOut
            ? "Account locked out. Try again in 5 minutes."
            : "Invalid username or password.";

        ReturnUrl = returnUrl;
        return Page();
    }
}

using MudBlazor;

namespace Automator.Web.Models;

public enum ScriptLanguage
{
    Bash,
    PowerShell,
    Python,
    Ansible
}

public static class ScriptLanguageExtensions
{
    public static string ToDisplayName(this ScriptLanguage lang) => lang switch
    {
        ScriptLanguage.Bash => "Bash",
        ScriptLanguage.PowerShell => "PowerShell",
        ScriptLanguage.Python => "Python",
        ScriptLanguage.Ansible => "Ansible Playbook",
        _ => lang.ToString()
    };

    public static string ToFileExtension(this ScriptLanguage lang) => lang switch
    {
        ScriptLanguage.Bash => ".sh",
        ScriptLanguage.PowerShell => ".ps1",
        ScriptLanguage.Python => ".py",
        ScriptLanguage.Ansible => ".yml",
        _ => ".txt"
    };

    public static string ToIconClass(this ScriptLanguage lang) => lang switch
    {
        ScriptLanguage.Bash => Icons.Material.Filled.Terminal,
        ScriptLanguage.PowerShell => Icons.Material.Filled.Window,
        ScriptLanguage.Python => Icons.Material.Filled.Code,
        ScriptLanguage.Ansible => Icons.Material.Filled.Settings,
        _ => Icons.Material.Filled.Code
    };

    public static Color ToColor(this ScriptLanguage lang) => lang switch
    {
        ScriptLanguage.Bash => Color.Success,
        ScriptLanguage.PowerShell => Color.Primary,
        ScriptLanguage.Python => Color.Warning,
        ScriptLanguage.Ansible => Color.Error,
        _ => Color.Default
    };
}

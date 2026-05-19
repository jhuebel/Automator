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
        ScriptLanguage.Bash => "bi-terminal",
        ScriptLanguage.PowerShell => "bi-window",
        ScriptLanguage.Python => "bi-filetype-py",
        ScriptLanguage.Ansible => "bi-gear-fill",
        _ => "bi-file-code"
    };

    public static string ToBadgeClass(this ScriptLanguage lang) => lang switch
    {
        ScriptLanguage.Bash => "bg-success",
        ScriptLanguage.PowerShell => "bg-primary",
        ScriptLanguage.Python => "bg-warning text-dark",
        ScriptLanguage.Ansible => "bg-danger",
        _ => "bg-secondary"
    };
}

namespace Automator.Web.Models;

public class ScriptVariable
{
    public string Name         { get; set; } = string.Empty;
    public string Description  { get; set; } = string.Empty;
    public string DefaultValue { get; set; } = string.Empty;
    public bool   Required     { get; set; }
}

namespace Automator.Web.Models;

public class ScriptDefinition
{
    public Guid Id { get; set; } = Guid.NewGuid();
    public string Name { get; set; } = string.Empty;
    public string Description { get; set; } = string.Empty;
    public ScriptLanguage Language { get; set; }
    public string Content { get; set; } = string.Empty;
    public List<string>         Tags      { get; set; } = [];
    public List<ScriptVariable> Variables { get; set; } = [];
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    public ScriptDefinition Clone() => new()
    {
        Id          = Id,
        Name        = Name,
        Description = Description,
        Language    = Language,
        Content     = Content,
        Tags        = [.. Tags],
        Variables   = Variables.Select(v => new ScriptVariable
        {
            Name         = v.Name,
            Type         = v.Type,
            Description  = v.Description,
            DefaultValue = v.DefaultValue,
            Required     = v.Required
        }).ToList(),
        CreatedAt = CreatedAt,
        UpdatedAt = UpdatedAt
    };
}

namespace Automator.Web.Models;

public class ScriptDefinition
{
    public Guid Id { get; set; } = Guid.NewGuid();
    public string Name { get; set; } = string.Empty;
    public string Description { get; set; } = string.Empty;
    public ScriptLanguage Language { get; set; }
    public string Content { get; set; } = string.Empty;
    public List<string> Tags { get; set; } = [];
    public DateTime CreatedAt { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAt { get; set; } = DateTime.UtcNow;

    public ScriptDefinition Clone() => new()
    {
        Id = Id,
        Name = Name,
        Description = Description,
        Language = Language,
        Content = Content,
        Tags = [.. Tags],
        CreatedAt = CreatedAt,
        UpdatedAt = UpdatedAt
    };
}

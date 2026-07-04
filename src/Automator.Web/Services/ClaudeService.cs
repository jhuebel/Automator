using System.Net.Http.Headers;
using System.Runtime.CompilerServices;
using System.Text;
using System.Text.Json;
using Automator.Web.Data;
using Microsoft.EntityFrameworkCore;

namespace Automator.Web.Services;

public class ClaudeService : IClaudeService
{
    private readonly HttpClient _http;
    private readonly string? _apiKey;
    private readonly string _model;
    private readonly string _effort;

    // Haiku models reject the effort parameter outright.
    private static bool SupportsEffort(string model) => !model.Contains("haiku", StringComparison.OrdinalIgnoreCase);

    public ClaudeService(IDbContextFactory<AutomatorDbContext> dbFactory, HttpClient http)
    {
        _http = http;

        using var db = dbFactory.CreateDbContext();
        var settings = db.Settings.Find(1);
        _apiKey = settings?.AnthropicApiKey;
        _model  = settings?.AnthropicModel ?? "claude-sonnet-5";
        _effort = settings?.AnthropicEffort ?? "high";
    }

    public bool IsConfigured => !string.IsNullOrWhiteSpace(_apiKey);

    public async IAsyncEnumerable<string> StreamAsync(
        string system, string user,
        [EnumeratorCancellation] CancellationToken ct = default)
    {
        if (!IsConfigured)
            throw new InvalidOperationException("Anthropic API key is not configured.");

        var payload = new Dictionary<string, object?>
        {
            ["model"]      = _model,
            ["max_tokens"] = 4096,
            ["stream"]     = true,
            ["system"]     = system,
            ["messages"]   = new[] { new { role = "user", content = user } }
        };
        if (SupportsEffort(_model))
            payload["output_config"] = new { effort = _effort };

        var body = JsonSerializer.Serialize(payload);

        using var request = new HttpRequestMessage(HttpMethod.Post, "https://api.anthropic.com/v1/messages");
        request.Headers.Add("x-api-key", _apiKey);
        request.Headers.Add("anthropic-version", "2023-06-01");
        request.Content = new StringContent(body, Encoding.UTF8, "application/json");

        using var response = await _http.SendAsync(request, HttpCompletionOption.ResponseHeadersRead, ct);
        response.EnsureSuccessStatusCode();

        using var stream = await response.Content.ReadAsStreamAsync(ct);
        using var reader = new StreamReader(stream);

        while (!reader.EndOfStream && !ct.IsCancellationRequested)
        {
            var line = await reader.ReadLineAsync(ct);
            if (line is null) break;
            if (!line.StartsWith("data:")) continue;

            var json = line["data:".Length..].Trim();
            if (json is "[DONE]" or "") continue;

            string? delta = null;
            try
            {
                using var doc = JsonDocument.Parse(json);
                var root = doc.RootElement;
                if (root.TryGetProperty("type", out var t) &&
                    t.GetString() == "content_block_delta" &&
                    root.TryGetProperty("delta", out var d) &&
                    d.TryGetProperty("text", out var text))
                {
                    delta = text.GetString();
                }
            }
            catch (JsonException) { }

            if (delta is not null)
                yield return delta;
        }
    }
}

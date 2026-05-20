namespace Automator.Web.Services;

public sealed class HelpDrawerState
{
    public bool IsOpen { get; private set; }
    public event Action? OnChange;
    public void Toggle() { IsOpen = !IsOpen; OnChange?.Invoke(); }
    public void Close()  { IsOpen = false;   OnChange?.Invoke(); }
}

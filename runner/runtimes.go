package main

import (
	"context"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
	"time"
)

// RuntimeCheck reports whether a script-language runtime is available on
// this runner host. The runner is the only host that actually executes
// scripts, so this — not the Laravel management plane — is the thing worth
// checking. Reported to the management plane alongside every heartbeat.
type RuntimeCheck struct {
	Name        string `json:"name"`
	Description string `json:"description"`
	Available   bool   `json:"available"`
	Version     string `json:"version,omitempty"`
	Path        string `json:"path,omitempty"`
	Error       string `json:"error,omitempty"`
}

type runtimeDef struct {
	name        string
	description string
	command     string
	versionArgs []string
}

var runtimeDefs = []runtimeDef{
	{"Bash", "Bourne Again Shell", "bash", []string{"--version"}},
	{"PowerShell Core", "Cross-platform PowerShell (pwsh)", "pwsh", []string{"--version"}},
	{"Python 3", "Python interpreter", "python3", []string{"--version"}},
	{"Ansible", "Ansible automation platform", "ansible-playbook", []string{"--version"}},
	{"Terraform", "Infrastructure as Code tool", "terraform", []string{"version"}},
}

// detectRuntimes checks each known script runtime's presence and reported
// version. Mirrors the checks Laravel used to run on its own host before
// script execution moved out to runners.
func detectRuntimes() []RuntimeCheck {
	checks := make([]RuntimeCheck, 0, len(runtimeDefs))
	for _, def := range runtimeDefs {
		checks = append(checks, detectRuntime(def))
	}

	return checks
}

func detectRuntime(def runtimeDef) RuntimeCheck {
	check := RuntimeCheck{Name: def.name, Description: def.description}

	path := findExecutable(def.command)
	if path == "" {
		check.Error = "Not found in PATH"

		return check
	}
	check.Path = path
	check.Available = true

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	// Invoke via the resolved absolute path, not the bare command name — a
	// match found through extraSearchDirs() below is by definition not on
	// PATH, so exec.Command(def.command, ...) would fail to find it again.
	out, _ := exec.CommandContext(ctx, path, def.versionArgs...).CombinedOutput()
	if firstLine := strings.SplitN(strings.TrimSpace(string(out)), "\n", 2)[0]; firstLine != "" {
		check.Version = firstLine
	}

	return check
}

// findExecutable resolves a command to an absolute path, first via the
// process's own PATH (the normal case), then by checking a handful of
// installation directories that a background service's PATH often doesn't
// include even though the tool is genuinely installed — e.g. Homebrew on
// Linux (/home/linuxbrew/.linuxbrew/bin) or a per-user prefix is normally
// only added to PATH by an interactive shell's rc file, which a systemd
// unit or Windows service never sources.
func findExecutable(command string) string {
	if path, err := exec.LookPath(command); err == nil {
		return path
	}

	name := command
	if runtime.GOOS == "windows" {
		name += ".exe"
	}

	for _, dir := range extraSearchDirs() {
		candidate := filepath.Join(dir, name)
		if info, err := os.Stat(candidate); err == nil && !info.IsDir() && isExecutable(info) {
			return candidate
		}
	}

	return ""
}

func extraSearchDirs() []string {
	if runtime.GOOS == "windows" {
		return []string{
			`C:\Program Files\PowerShell\7`,
			`C:\Program Files\PowerShell\7-preview`,
		}
	}

	dirs := []string{
		"/usr/local/bin",
		"/snap/bin",
		"/home/linuxbrew/.linuxbrew/bin",
	}

	if home, err := os.UserHomeDir(); err == nil && home != "" {
		dirs = append(dirs, filepath.Join(home, ".linuxbrew", "bin"), filepath.Join(home, ".local", "bin"))
	}

	// Microsoft's official .deb/.rpm packages install under a
	// version-numbered directory (e.g. /opt/microsoft/powershell/7) rather
	// than a fixed path — glob instead of hardcoding a version.
	if matches, err := filepath.Glob("/opt/microsoft/powershell/*"); err == nil {
		dirs = append(dirs, matches...)
	}

	return dirs
}

func isExecutable(info os.FileInfo) bool {
	// On Windows this is moot (no unix-style permission bits — presence at
	// a .exe-suffixed path is enough, already handled by findExecutable).
	return runtime.GOOS == "windows" || info.Mode()&0o111 != 0
}

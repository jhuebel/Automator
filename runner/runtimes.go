package main

import (
	"context"
	"os/exec"
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

// detectRuntimes checks each known script runtime's presence on PATH and its
// reported version. Mirrors the checks Laravel used to run on its own host
// before script execution moved out to runners.
func detectRuntimes() []RuntimeCheck {
	checks := make([]RuntimeCheck, 0, len(runtimeDefs))
	for _, def := range runtimeDefs {
		checks = append(checks, detectRuntime(def))
	}

	return checks
}

func detectRuntime(def runtimeDef) RuntimeCheck {
	check := RuntimeCheck{Name: def.name, Description: def.description}

	path, err := exec.LookPath(def.command)
	if err != nil {
		check.Error = "Not found in PATH"

		return check
	}
	check.Path = path
	check.Available = true

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	out, _ := exec.CommandContext(ctx, def.command, def.versionArgs...).CombinedOutput()
	if firstLine := strings.SplitN(strings.TrimSpace(string(out)), "\n", 2)[0]; firstLine != "" {
		check.Version = firstLine
	}

	return check
}

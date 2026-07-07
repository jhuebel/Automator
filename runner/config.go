package main

import (
	"encoding/json"
	"errors"
	"os"
	"path/filepath"
	"runtime"
)

// Config is the runner's persisted local state, written once during
// `register` and read on every `run`.
type Config struct {
	Server   string   `json:"server"`
	Token    string   `json:"token"`
	RunnerID string   `json:"runner_id"`
	Name     string   `json:"name"`
	Tags     []string `json:"tags"`
	Reverb   struct {
		Key    string `json:"key"`
		Host   string `json:"host"`
		Port   int    `json:"port"`
		Scheme string `json:"scheme"`
	} `json:"reverb"`
}

// defaultConfigPath returns the OS-appropriate default location for the
// runner's config file. Kept simple on purpose: file permissions (0600 on
// Linux, ACL restriction documented for Windows install) are the security
// boundary, not anything fancier.
func defaultConfigPath() string {
	if runtime.GOOS == "windows" {
		programData := os.Getenv("ProgramData")
		if programData == "" {
			programData = `C:\ProgramData`
		}

		return filepath.Join(programData, "AutomatorRunner", "config.json")
	}

	if os.Geteuid() == 0 {
		return "/etc/automator-runner/config.json"
	}

	home, err := os.UserHomeDir()
	if err != nil {
		return "automator-runner-config.json"
	}

	return filepath.Join(home, ".config", "automator-runner", "config.json")
}

func loadConfig(path string) (*Config, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}

	var cfg Config
	if err := json.Unmarshal(data, &cfg); err != nil {
		return nil, err
	}

	if cfg.Server == "" || cfg.Token == "" || cfg.RunnerID == "" {
		return nil, errors.New("config file is incomplete — re-run register")
	}

	return &cfg, nil
}

func saveConfig(path string, cfg *Config) error {
	if err := os.MkdirAll(filepath.Dir(path), 0o700); err != nil {
		return err
	}

	data, err := json.MarshalIndent(cfg, "", "  ")
	if err != nil {
		return err
	}

	return os.WriteFile(path, data, 0o600)
}

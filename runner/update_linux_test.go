//go:build linux

package main

import (
	"os"
	"path/filepath"
	"testing"
)

func TestReplaceExecutableLinux(t *testing.T) {
	dir := t.TempDir()
	exePath := filepath.Join(dir, "automator-runner")

	if err := os.WriteFile(exePath, []byte("old binary"), 0o755); err != nil {
		t.Fatalf("failed to seed original binary: %v", err)
	}

	newData := []byte("new binary contents")
	if err := replaceExecutable(exePath, newData); err != nil {
		t.Fatalf("replaceExecutable failed: %v", err)
	}

	got, err := os.ReadFile(exePath)
	if err != nil {
		t.Fatalf("failed to read replaced binary: %v", err)
	}
	if string(got) != string(newData) {
		t.Fatalf("expected replaced contents %q, got %q", newData, got)
	}

	info, err := os.Stat(exePath)
	if err != nil {
		t.Fatalf("failed to stat replaced binary: %v", err)
	}
	if info.Mode().Perm()&0o100 == 0 {
		t.Fatalf("expected replaced binary to be executable, got mode %v", info.Mode())
	}

	entries, err := os.ReadDir(dir)
	if err != nil {
		t.Fatalf("failed to list temp dir: %v", err)
	}
	if len(entries) != 1 {
		t.Fatalf("expected exactly one file left in the directory (no leftover temp file), got %d", len(entries))
	}
}

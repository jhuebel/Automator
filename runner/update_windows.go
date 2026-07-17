//go:build windows

package main

import "os"

// replaceExecutable replaces the running exe on Windows, which refuses to
// overwrite an in-use .exe directly but allows renaming one aside. The old
// binary (renamed to .old) is left behind — Windows won't let a running
// process delete its own backing file either — and is harmless clutter, not
// cleaned up here.
func replaceExecutable(exePath string, data []byte) error {
	oldPath := exePath + ".old"
	_ = os.Remove(oldPath) // leftover from a previous update, if any

	if err := os.Rename(exePath, oldPath); err != nil {
		return err
	}

	if err := os.WriteFile(exePath, data, 0o755); err != nil {
		_ = os.Rename(oldPath, exePath) // best-effort rollback
		return err
	}

	return nil
}

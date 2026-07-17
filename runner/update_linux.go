//go:build linux

package main

import (
	"os"
	"path/filepath"
)

// replaceExecutable atomically replaces the binary at exePath with data.
// Safe even while this process is currently running from exePath — Linux
// keeps the old inode alive via this process's open reference, and the
// rename just repoints the directory entry to the new inode. The temp file
// is created in the same directory so the rename stays on one filesystem.
func replaceExecutable(exePath string, data []byte) error {
	dir := filepath.Dir(exePath)
	tmp, err := os.CreateTemp(dir, ".automator-runner-update-*")
	if err != nil {
		return err
	}
	tmpPath := tmp.Name()
	defer os.Remove(tmpPath) // no-op once the rename below succeeds

	if _, err := tmp.Write(data); err != nil {
		tmp.Close()
		return err
	}
	if err := tmp.Close(); err != nil {
		return err
	}

	if err := os.Chmod(tmpPath, 0o755); err != nil {
		return err
	}

	return os.Rename(tmpPath, exePath)
}

//go:build linux

package main

import "golang.org/x/sys/unix"

// diskSpace reports free/total bytes on the filesystem containing path.
func diskSpace(path string) (free, total uint64, err error) {
	var stat unix.Statfs_t
	if err := unix.Statfs(path, &stat); err != nil {
		return 0, 0, err
	}

	// Bavail (available to unprivileged users) rather than Bfree (raw free,
	// which includes space reserved for root) — matches what a script
	// actually has room to write, not the filesystem's total free space.
	free = stat.Bavail * uint64(stat.Bsize)
	total = stat.Blocks * uint64(stat.Bsize)

	return free, total, nil
}

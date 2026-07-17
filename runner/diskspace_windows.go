//go:build windows

package main

import "golang.org/x/sys/windows"

// diskSpace reports free/total bytes on the volume containing path.
func diskSpace(path string) (free, total uint64, err error) {
	pathPtr, err := windows.UTF16PtrFromString(path)
	if err != nil {
		return 0, 0, err
	}

	var freeBytesAvailable, totalBytes, totalFreeBytes uint64
	if err := windows.GetDiskFreeSpaceEx(pathPtr, &freeBytesAvailable, &totalBytes, &totalFreeBytes); err != nil {
		return 0, 0, err
	}

	// freeBytesAvailable (caller's quota-adjusted free space) rather than
	// totalFreeBytes — matches what this process actually has room to write.
	return freeBytesAvailable, totalBytes, nil
}

//go:build linux

package main

import (
	"os/exec"
	"syscall"
)

func terminateProcess(cmd *exec.Cmd) {
	if cmd.Process == nil {
		return
	}
	_ = cmd.Process.Signal(syscall.SIGTERM)
}

//go:build windows

package main

import (
	"os/exec"
	"syscall"
)

// prepareProcess starts the child in its own process group so a
// CTRL_BREAK_EVENT can be targeted at it without also signaling this runner
// process itself.
func prepareProcess(cmd *exec.Cmd) {
	cmd.SysProcAttr = &syscall.SysProcAttr{
		CreationFlags: syscall.CREATE_NEW_PROCESS_GROUP,
	}
}

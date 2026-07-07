//go:build windows

package main

import (
	"log"
	"os/exec"
	"time"

	"golang.org/x/sys/windows"
)

// terminateProcess sends a graceful CTRL_BREAK_EVENT to the process (started
// in its own process group by prepareProcess) and falls back to a hard
// TerminateProcess kill after a short grace period if it hasn't exited —
// Go's Process.Kill() on Windows always maps to TerminateProcess, there is
// no SIGTERM equivalent to send directly.
func terminateProcess(cmd *exec.Cmd) {
	if cmd.Process == nil {
		return
	}

	pid := uint32(cmd.Process.Pid)

	if err := windows.GenerateConsoleCtrlEvent(windows.CTRL_BREAK_EVENT, pid); err != nil {
		log.Printf("CTRL_BREAK_EVENT failed, hard killing: %v", err)
		_ = cmd.Process.Kill()

		return
	}

	go func() {
		time.Sleep(5 * time.Second)
		// Kill() on an already-exited process just returns an error, which is
		// fine to ignore — this is a best-effort fallback, not the primary path.
		_ = cmd.Process.Kill()
	}()
}

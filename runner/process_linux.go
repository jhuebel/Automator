//go:build linux

package main

import "os/exec"

// prepareProcess is a no-op on Linux — SIGTERM to the direct child is
// sufficient for the shells/interpreters this runner spawns.
func prepareProcess(cmd *exec.Cmd) {}

package main

import (
	"os/exec"
	"testing"
)

func TestExecutorIdle(t *testing.T) {
	e := newExecutor(nil)

	if !e.Idle() {
		t.Fatalf("expected a freshly created executor to be idle")
	}

	e.register("exec-1", &exec.Cmd{})
	if e.Idle() {
		t.Fatalf("expected executor to be busy while an execution is registered")
	}

	e.unregister("exec-1")
	if !e.Idle() {
		t.Fatalf("expected executor to be idle again after unregistering its only execution")
	}
}

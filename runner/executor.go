package main

import (
	"bufio"
	"context"
	"fmt"
	"io"
	"log"
	"os"
	"os/exec"
	"path/filepath"
	"sync"
	"time"
)

type Executor struct {
	api *apiClient

	mu      sync.Mutex
	running map[string]*exec.Cmd
}

func newExecutor(api *apiClient) *Executor {
	return &Executor{api: api, running: make(map[string]*exec.Cmd)}
}

func (e *Executor) Cancel(executionID string) {
	e.mu.Lock()
	cmd, ok := e.running[executionID]
	e.mu.Unlock()

	if !ok {
		return
	}

	log.Printf("[%s] cancel requested — signaling process", executionID)
	terminateProcess(cmd)
}

func (e *Executor) register(executionID string, cmd *exec.Cmd) {
	e.mu.Lock()
	e.running[executionID] = cmd
	e.mu.Unlock()
}

func (e *Executor) unregister(executionID string) {
	e.mu.Lock()
	delete(e.running, executionID)
	e.mu.Unlock()
}

// RunJob executes an assigned script and reports output/completion back to
// the management plane. It never returns an error to the caller — any
// failure is reported via the normal finish() call with a non-zero exit code,
// exactly like a failing script would be.
func (e *Executor) RunJob(payload jobAssignedPayload) {
	log.Printf("[%s] starting %s job", payload.ExecutionID, payload.Language)

	reporter := newOutputReporter(e.api, payload.ExecutionID)

	var exitCode int
	var err error

	if payload.Language == "Terraform" {
		exitCode, err = e.runTerraform(payload, reporter)
	} else {
		exitCode, err = e.runScript(payload, reporter)
	}

	if err != nil {
		reporter.line(fmt.Sprintf("Execution error: %s", err), true)
		exitCode = -1
	}

	reporter.flush()

	if ferr := e.api.finish(payload.ExecutionID, exitCode); ferr != nil {
		log.Printf("[%s] failed to report finish: %v", payload.ExecutionID, ferr)
	}

	log.Printf("[%s] finished, exit code %d", payload.ExecutionID, exitCode)
}

func (e *Executor) runScript(payload jobAssignedPayload, reporter *outputReporter) (int, error) {
	tempFile, err := os.CreateTemp("", "automator_*"+fileExtension(payload.Language))
	if err != nil {
		return -1, err
	}
	defer os.Remove(tempFile.Name())

	if _, err := tempFile.WriteString(payload.Content); err != nil {
		tempFile.Close()
		return -1, err
	}
	tempFile.Close()

	args, err := commandFor(payload.Language, tempFile.Name())
	if err != nil {
		return -1, err
	}

	return e.run(payload.ExecutionID, args, "", payload.Variables, payload.TimeoutSeconds, reporter)
}

func (e *Executor) runTerraform(payload jobAssignedPayload, reporter *outputReporter) (int, error) {
	tempDir, err := os.MkdirTemp("", "automator_"+payload.ExecutionID+"_tf")
	if err != nil {
		return -1, err
	}
	defer os.RemoveAll(tempDir)

	if err := os.WriteFile(filepath.Join(tempDir, "main.tf"), []byte(payload.Content), 0o600); err != nil {
		return -1, err
	}

	tfVars := make(map[string]string, len(payload.Variables))
	for k, v := range payload.Variables {
		tfVars["TF_VAR_"+k] = v
	}

	reporter.line("==> terraform init", false)
	exitCode, err := e.run(payload.ExecutionID, []string{"terraform", "init", "-no-color"}, tempDir, tfVars, payload.TimeoutSeconds, reporter)
	if err != nil || exitCode != 0 {
		return exitCode, err
	}

	reporter.line("==> terraform apply", false)
	return e.run(payload.ExecutionID, []string{"terraform", "apply", "-auto-approve", "-no-color"}, tempDir, tfVars, payload.TimeoutSeconds, reporter)
}

func (e *Executor) run(executionID string, args []string, dir string, env map[string]string, timeoutSeconds int, reporter *outputReporter) (int, error) {
	ctx, cancel := context.WithTimeout(context.Background(), time.Duration(timeoutSeconds)*time.Second)
	defer cancel()

	cmd := exec.CommandContext(ctx, args[0], args[1:]...)
	if dir != "" {
		cmd.Dir = dir
	}

	cmd.Env = os.Environ()
	for k, v := range env {
		cmd.Env = append(cmd.Env, k+"="+v)
	}

	stdout, err := cmd.StdoutPipe()
	if err != nil {
		return -1, err
	}
	stderr, err := cmd.StderrPipe()
	if err != nil {
		return -1, err
	}

	prepareProcess(cmd)

	if err := cmd.Start(); err != nil {
		return -1, err
	}

	e.register(executionID, cmd)
	defer e.unregister(executionID)

	var wg sync.WaitGroup
	wg.Add(2)
	go streamLines(&wg, stdout, false, reporter)
	go streamLines(&wg, stderr, true, reporter)
	wg.Wait()

	err = cmd.Wait()

	if ctx.Err() == context.DeadlineExceeded {
		reporter.line(fmt.Sprintf("Execution timed out after %ds.", timeoutSeconds), true)
		return -1, nil
	}

	if err == nil {
		return 0, nil
	}

	if exitErr, ok := err.(*exec.ExitError); ok {
		return exitErr.ExitCode(), nil
	}

	// Process was signaled (cancelled) or failed to start cleanly.
	return -1, nil
}

func streamLines(wg *sync.WaitGroup, r io.Reader, isError bool, reporter *outputReporter) {
	defer wg.Done()

	scanner := bufio.NewScanner(r)
	scanner.Buffer(make([]byte, 0, 64*1024), 1024*1024)
	for scanner.Scan() {
		reporter.line(scanner.Text(), isError)
	}
}

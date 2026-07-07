package main

import (
	"log"
	"sync"
	"time"
)

// outputReporter batches output lines and flushes them to the management
// plane roughly every 250ms, mirroring RunScriptJob's flush cadence so a
// chatty script doesn't turn into one HTTP request per line.
type outputReporter struct {
	api         *apiClient
	executionID string

	mu       sync.Mutex
	buffer   []outputLine
	lastSent time.Time
}

func newOutputReporter(api *apiClient, executionID string) *outputReporter {
	return &outputReporter{api: api, executionID: executionID, lastSent: time.Now()}
}

func (r *outputReporter) line(text string, isError bool) {
	r.mu.Lock()
	r.buffer = append(r.buffer, outputLine{
		Text:      text,
		IsError:   isError,
		Timestamp: time.Now().UTC().Format(time.RFC3339),
	})
	shouldFlush := time.Since(r.lastSent) > 250*time.Millisecond
	r.mu.Unlock()

	if shouldFlush {
		r.flush()
	}
}

func (r *outputReporter) flush() {
	r.mu.Lock()
	if len(r.buffer) == 0 {
		r.mu.Unlock()
		return
	}
	batch := r.buffer
	r.buffer = nil
	r.lastSent = time.Now()
	r.mu.Unlock()

	if err := r.api.sendOutput(r.executionID, batch); err != nil {
		log.Printf("[%s] failed to send output batch: %v", r.executionID, err)
	}
}

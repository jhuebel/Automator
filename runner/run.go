package main

import (
	"flag"
	"fmt"
	"log"
	"os"
	"time"
)

func runDaemon(args []string) {
	fs := flag.NewFlagSet("run", flag.ExitOnError)
	configPath := fs.String("config", defaultConfigPath(), "Path to the runner's config file")
	_ = fs.Parse(args)

	cfg, err := loadConfig(*configPath)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Failed to load config from %s: %v\nRun 'automator-runner register' first.\n", *configPath, err)
		os.Exit(1)
	}

	api := newAPIClient(cfg.Server, cfg.Token)
	executor := newExecutor(api)

	go heartbeatLoop(api)

	client := newPusherClient(cfg, api, executor.RunJob, func(payload jobCancelPayload) {
		executor.Cancel(payload.ExecutionID)
	})

	log.Printf("automator-runner %q starting, connecting to %s", cfg.Name, cfg.Server)
	client.runWithReconnect()
}

// heartbeatLoop reports liveness every 15s. Runtime availability is detected
// once at startup rather than on every tick — the installed toolchain on a
// runner host doesn't change minute to minute, and shelling out to five
// version checks every 15s would be wasteful.
func heartbeatLoop(api *apiClient) {
	runtimes := detectRuntimes()

	if err := api.heartbeat(runtimes); err != nil {
		log.Printf("heartbeat failed: %v", err)
	}

	ticker := time.NewTicker(15 * time.Second)
	defer ticker.Stop()

	for range ticker.C {
		if err := api.heartbeat(runtimes); err != nil {
			log.Printf("heartbeat failed: %v", err)
		}
	}
}

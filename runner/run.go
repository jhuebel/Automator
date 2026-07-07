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

func heartbeatLoop(api *apiClient) {
	ticker := time.NewTicker(15 * time.Second)
	defer ticker.Stop()

	for range ticker.C {
		if err := api.heartbeat(); err != nil {
			log.Printf("heartbeat failed: %v", err)
		}
	}
}

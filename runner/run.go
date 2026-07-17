package main

import (
	"flag"
	"fmt"
	"log"
	"os"
	"runtime"
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

	go heartbeatLoop(api, executor)

	client := newPusherClient(cfg, api, executor.RunJob, func(payload jobCancelPayload) {
		executor.Cancel(payload.ExecutionID)
	})

	log.Printf("automator-runner %q starting, connecting to %s", cfg.Name, cfg.Server)
	client.runWithReconnect()
}

// heartbeatLoop reports liveness every 15s. Runtime availability, version,
// and CPU architecture are detected once at startup rather than on every
// tick — none of them change minute to minute for a running process, and
// shelling out to five version checks every 15s would be wasteful. Disk
// space is re-checked on every tick, since a long-running script (or
// several) can genuinely fill up the runner's temp directory over time.
func heartbeatLoop(api *apiClient, executor *Executor) {
	runtimes := detectRuntimes()
	arch := runtime.GOARCH

	send := func() {
		free, total, err := diskSpace(os.TempDir())
		if err != nil {
			log.Printf("disk space check failed: %v", err)
		}

		info := heartbeatInfo{Version: Version, Arch: arch, DiskFreeBytes: free, DiskTotalBytes: total}
		update, err := api.heartbeat(runtimes, info)
		if err != nil {
			log.Printf("heartbeat failed: %v", err)
			return
		}

		if update != nil {
			maybeApplyUpdate(api, executor, *update)
		}
	}

	send()

	ticker := time.NewTicker(15 * time.Second)
	defer ticker.Stop()

	for range ticker.C {
		send()
	}
}

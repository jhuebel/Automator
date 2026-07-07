package main

import (
	"flag"
	"fmt"
	"os"
)

func main() {
	if len(os.Args) < 2 {
		printUsage()
		os.Exit(1)
	}

	switch os.Args[1] {
	case "register":
		runRegister(os.Args[2:])
	case "run":
		runDaemon(os.Args[2:])
	case "unregister":
		runUnregister(os.Args[2:])
	case "-h", "--help", "help":
		printUsage()
	default:
		fmt.Fprintf(os.Stderr, "Unknown command: %s\n\n", os.Args[1])
		printUsage()
		os.Exit(1)
	}
}

func printUsage() {
	fmt.Println(`automator-runner — execution agent for Automator

Usage:
  automator-runner register --server <url> --token <token> --name <name> [--tags a,b,c]
  automator-runner run [--config path]
  automator-runner unregister [--config path]`)
}

func runUnregister(args []string) {
	fs := flag.NewFlagSet("unregister", flag.ExitOnError)
	configPath := fs.String("config", defaultConfigPath(), "Path to the runner's config file")
	_ = fs.Parse(args)

	cfg, err := loadConfig(*configPath)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Failed to load config from %s: %v\n", *configPath, err)
		os.Exit(1)
	}

	api := newAPIClient(cfg.Server, cfg.Token)
	if err := api.unregister(); err != nil {
		fmt.Fprintf(os.Stderr, "Failed to unregister: %v\n", err)
		os.Exit(1)
	}

	if err := os.Remove(*configPath); err != nil && !os.IsNotExist(err) {
		fmt.Fprintf(os.Stderr, "Unregistered, but failed to remove config file: %v\n", err)
		os.Exit(1)
	}

	fmt.Println("Runner unregistered.")
}

package main

import (
	"flag"
	"fmt"
	"os"
	"runtime"
	"strings"
)

func runRegister(args []string) {
	fs := flag.NewFlagSet("register", flag.ExitOnError)
	server := fs.String("server", "", "Management plane base URL, e.g. https://automator.example.com")
	token := fs.String("token", "", "One-time enrollment token from Settings > Runners")
	name := fs.String("name", "", "Unique name for this runner")
	tagsFlag := fs.String("tags", "", "Comma-separated capability tags, e.g. linux,terraform")
	configPath := fs.String("config", defaultConfigPath(), "Where to write the runner's config file")
	_ = fs.Parse(args)

	if *server == "" || *token == "" || *name == "" {
		fmt.Fprintln(os.Stderr, "Usage: automator-runner register --server <url> --token <token> --name <name> [--tags a,b,c] [--config path]")
		os.Exit(1)
	}

	hostname, _ := os.Hostname()

	var tags []string
	if strings.TrimSpace(*tagsFlag) != "" {
		for _, t := range strings.Split(*tagsFlag, ",") {
			if t = strings.TrimSpace(t); t != "" {
				tags = append(tags, t)
			}
		}
	}

	client := newAPIClient(strings.TrimRight(*server, "/"), *token)

	resp, err := client.register(*name, hostname, runtime.GOOS, tags)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Registration failed: %v\n", err)
		os.Exit(1)
	}

	cfg := &Config{
		Server:   strings.TrimRight(*server, "/"),
		Token:    resp.Token,
		RunnerID: resp.RunnerID,
		Name:     *name,
		Tags:     tags,
	}
	cfg.Reverb.Key = resp.Reverb.Key
	cfg.Reverb.Host = resp.Reverb.Host
	cfg.Reverb.Port = resp.Reverb.Port
	cfg.Reverb.Scheme = resp.Reverb.Scheme

	if err := saveConfig(*configPath, cfg); err != nil {
		fmt.Fprintf(os.Stderr, "Registered, but failed to save config to %s: %v\n", *configPath, err)
		os.Exit(1)
	}

	fmt.Printf("Registered runner %q (id %s). Config saved to %s.\n", *name, resp.RunnerID, *configPath)
	fmt.Println("Start it with: automator-runner run --config", *configPath)
}

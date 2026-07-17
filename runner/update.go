package main

import (
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"log"
	"os"
)

// updateInfo is the optional "update" payload a heartbeat response includes
// when a newer, released binary exists for this runner's os/arch and the
// management plane's global auto-update toggle is on.
type updateInfo struct {
	Version        string `json:"version"`
	ChecksumSHA256 string `json:"checksum_sha256"`
	SizeBytes      int64  `json:"size_bytes"`
	DownloadURL    string `json:"download_url"`
}

// maybeApplyUpdate downloads and applies a newer runner binary advertised in
// a heartbeat response, then exits so the process supervisor (systemd /
// NSSM) relaunches from the replaced binary. It never interrupts a running
// job — if one is in flight, this update is simply retried on the next
// heartbeat tick.
func maybeApplyUpdate(api *apiClient, executor *Executor, info updateInfo) {
	if !executor.Idle() {
		log.Printf("update to %s available but a job is running — deferring", info.Version)
		return
	}

	log.Printf("downloading update to %s...", info.Version)
	data, err := api.downloadRelease(info.DownloadURL)
	if err != nil {
		log.Printf("update download failed: %v", err)
		return
	}

	if err := verifyChecksum(data, info.ChecksumSHA256); err != nil {
		log.Printf("update checksum verification failed: %v", err)
		return
	}

	exePath, err := os.Executable()
	if err != nil {
		log.Printf("update failed: could not determine own executable path: %v", err)
		return
	}

	if err := replaceExecutable(exePath, data); err != nil {
		log.Printf("update failed: %v", err)
		return
	}

	log.Printf("updated to %s, exiting for restart", info.Version)
	os.Exit(0)
}

func verifyChecksum(data []byte, want string) error {
	sum := sha256.Sum256(data)
	got := hex.EncodeToString(sum[:])
	if got != want {
		return fmt.Errorf("checksum mismatch: got %s, want %s", got, want)
	}
	return nil
}

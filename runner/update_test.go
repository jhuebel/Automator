package main

import (
	"crypto/sha256"
	"encoding/hex"
	"testing"
)

func TestVerifyChecksum(t *testing.T) {
	data := []byte("automator-runner binary contents")
	sum := sha256.Sum256(data)
	correct := hex.EncodeToString(sum[:])

	if err := verifyChecksum(data, correct); err != nil {
		t.Fatalf("expected matching checksum to pass, got: %v", err)
	}

	if err := verifyChecksum(data, "0000000000000000000000000000000000000000000000000000000000000"); err == nil {
		t.Fatalf("expected a bogus checksum to be refused, got nil error")
	}

	if err := verifyChecksum([]byte("different contents"), correct); err == nil {
		t.Fatalf("expected mismatched data to be refused, got nil error")
	}
}

package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"time"
)

type apiClient struct {
	server string
	token  string
	http   *http.Client
}

func newAPIClient(server, token string) *apiClient {
	return &apiClient{
		server: server,
		token:  token,
		http:   &http.Client{Timeout: 15 * time.Second},
	}
}

func (c *apiClient) post(path string, body any) ([]byte, int, error) {
	var reader io.Reader
	if body != nil {
		data, err := json.Marshal(body)
		if err != nil {
			return nil, 0, err
		}
		reader = bytes.NewReader(data)
	}

	req, err := http.NewRequest(http.MethodPost, c.server+path, reader)
	if err != nil {
		return nil, 0, err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	if c.token != "" {
		req.Header.Set("Authorization", "Bearer "+c.token)
	}

	resp, err := c.http.Do(req)
	if err != nil {
		return nil, 0, err
	}
	defer resp.Body.Close()

	respBody, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, resp.StatusCode, err
	}

	return respBody, resp.StatusCode, nil
}

type registerResponse struct {
	RunnerID string `json:"runner_id"`
	Token    string `json:"token"`
	Reverb   struct {
		Key    string `json:"key"`
		Host   string `json:"host"`
		Port   int    `json:"port"`
		Scheme string `json:"scheme"`
	} `json:"reverb"`
}

func (c *apiClient) register(name, hostname, os string, tags []string) (*registerResponse, error) {
	body, status, err := c.post("/api/runner/register", map[string]any{
		"token":    c.token,
		"name":     name,
		"hostname": hostname,
		"os":       os,
		"tags":     tags,
	})
	if err != nil {
		return nil, err
	}

	if status != 201 {
		var errBody struct {
			Message string `json:"message"`
		}
		_ = json.Unmarshal(body, &errBody)
		if errBody.Message == "" {
			errBody.Message = string(body)
		}
		return nil, fmt.Errorf("registration failed (HTTP %d): %s", status, errBody.Message)
	}

	var out registerResponse
	if err := json.Unmarshal(body, &out); err != nil {
		return nil, err
	}

	return &out, nil
}

// authorizeChannel calls Laravel's broadcasting auth endpoint to get a signed
// auth string for a private channel, the same endpoint the browser's Echo
// client uses — just authenticated with our bearer token instead of a
// session cookie (routes/channels.php grants the runner.{id} channel to the
// 'sanctum' guard).
func (c *apiClient) authorizeChannel(channelName, socketID string) (string, error) {
	body, status, err := c.post("/broadcasting/auth", map[string]any{
		"channel_name": channelName,
		"socket_id":    socketID,
	})
	if err != nil {
		return "", err
	}
	if status != 200 {
		return "", fmt.Errorf("channel auth failed (HTTP %d): %s", status, string(body))
	}

	var out struct {
		Auth string `json:"auth"`
	}
	if err := json.Unmarshal(body, &out); err != nil {
		return "", err
	}

	return out.Auth, nil
}

// heartbeatInfo is the runner-identity portion of a heartbeat — separate
// from RuntimeCheck (language availability) since these describe the
// runner's own build/host rather than what it can execute.
type heartbeatInfo struct {
	Version        string `json:"version"`
	Arch           string `json:"arch"`
	DiskFreeBytes  uint64 `json:"disk_free_bytes"`
	DiskTotalBytes uint64 `json:"disk_total_bytes"`
}

type heartbeatResponse struct {
	Status string      `json:"status"`
	Update *updateInfo `json:"update"`
}

// heartbeat returns the "update" field from the response, if the management
// plane included one — nil in the common case (auto-update off, no eligible
// release, or this runner is already current).
func (c *apiClient) heartbeat(runtimes []RuntimeCheck, info heartbeatInfo) (*updateInfo, error) {
	body, status, err := c.post("/api/runner/heartbeat", map[string]any{
		"runtimes":         runtimes,
		"version":          info.Version,
		"arch":             info.Arch,
		"disk_free_bytes":  info.DiskFreeBytes,
		"disk_total_bytes": info.DiskTotalBytes,
	})
	if err != nil {
		return nil, err
	}
	if status != 200 {
		return nil, fmt.Errorf("heartbeat failed (HTTP %d)", status)
	}

	var resp heartbeatResponse
	if err := json.Unmarshal(body, &resp); err != nil {
		return nil, err
	}

	return resp.Update, nil
}

// downloadRelease fetches a runner binary from a fully-qualified URL (as
// given by a heartbeat response's update.download_url) using the same
// bearer token as every other authenticated call. Uses its own client with
// a longer timeout than c.http's 15s, since a binary download can
// legitimately take longer than the other, tiny JSON exchanges.
func (c *apiClient) downloadRelease(url string) ([]byte, error) {
	req, err := http.NewRequest(http.MethodGet, url, nil)
	if err != nil {
		return nil, err
	}
	if c.token != "" {
		req.Header.Set("Authorization", "Bearer "+c.token)
	}

	client := &http.Client{Timeout: 2 * time.Minute}
	resp, err := client.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		return nil, fmt.Errorf("download failed (HTTP %d)", resp.StatusCode)
	}

	return io.ReadAll(resp.Body)
}

func (c *apiClient) unregister() error {
	_, status, err := c.post("/api/runner/unregister", nil)
	if err != nil {
		return err
	}
	if status != 200 {
		return fmt.Errorf("unregister failed (HTTP %d)", status)
	}
	return nil
}

type outputLine struct {
	Text      string `json:"text"`
	IsError   bool   `json:"is_error"`
	Timestamp string `json:"timestamp"`
}

func (c *apiClient) sendOutput(executionID string, lines []outputLine) error {
	_, status, err := c.post(fmt.Sprintf("/api/runner/executions/%s/output", executionID), map[string]any{
		"lines": lines,
	})
	if err != nil {
		return err
	}
	if status != 200 {
		return fmt.Errorf("output report failed (HTTP %d)", status)
	}
	return nil
}

func (c *apiClient) finish(executionID string, exitCode int) error {
	_, status, err := c.post(fmt.Sprintf("/api/runner/executions/%s/finish", executionID), map[string]any{
		"exit_code": exitCode,
	})
	if err != nil {
		return err
	}
	if status != 200 {
		return fmt.Errorf("finish report failed (HTTP %d)", status)
	}
	return nil
}

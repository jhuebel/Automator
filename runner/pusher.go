package main

import (
	"encoding/json"
	"fmt"
	"log"
	"net/url"
	"strings"
	"time"

	"github.com/gorilla/websocket"
)

// pusherFrame is the wire envelope every Pusher-protocol message uses.
// Incoming frames carry `data` as a JSON-encoded *string* (Pusher protocol
// convention); outgoing frames (subscribe) carry `data` as a raw object.
type pusherFrame struct {
	Event   string          `json:"event"`
	Channel string          `json:"channel,omitempty"`
	Data    json.RawMessage `json:"data,omitempty"`
}

type jobAssignedPayload struct {
	ExecutionID    string            `json:"execution_id"`
	Language       string            `json:"language"`
	Content        string            `json:"content"`
	Variables      map[string]string `json:"variables"`
	TimeoutSeconds int               `json:"timeout_seconds"`
}

type jobCancelPayload struct {
	ExecutionID string `json:"execution_id"`
}

// pusherClient maintains a connection to Reverb for exactly one private
// channel (this runner's own `runner.{id}` channel) and dispatches the two
// application events we care about.
type pusherClient struct {
	cfg    *Config
	api    *apiClient
	conn   *websocket.Conn
	onJob  func(jobAssignedPayload)
	onStop func(jobCancelPayload)
}

func newPusherClient(cfg *Config, api *apiClient, onJob func(jobAssignedPayload), onStop func(jobCancelPayload)) *pusherClient {
	return &pusherClient{cfg: cfg, api: api, onJob: onJob, onStop: onStop}
}

func (p *pusherClient) connectAndListen() error {
	wsScheme := "ws"
	if p.cfg.Reverb.Scheme == "https" {
		wsScheme = "wss"
	}

	u := url.URL{
		Scheme:   wsScheme,
		Host:     fmt.Sprintf("%s:%d", p.cfg.Reverb.Host, p.cfg.Reverb.Port),
		Path:     fmt.Sprintf("/app/%s", p.cfg.Reverb.Key),
		RawQuery: "protocol=7&client=automator-runner&version=1.0",
	}

	conn, _, err := websocket.DefaultDialer.Dial(u.String(), nil)
	if err != nil {
		return fmt.Errorf("dial reverb: %w", err)
	}
	p.conn = conn
	defer conn.Close()

	channelName := "private-runner." + p.cfg.RunnerID

	for {
		_, message, err := conn.ReadMessage()
		if err != nil {
			return fmt.Errorf("read: %w", err)
		}

		var frame pusherFrame
		if err := json.Unmarshal(message, &frame); err != nil {
			log.Printf("skipping malformed frame: %v", err)
			continue
		}

		switch frame.Event {
		case "pusher:connection_established":
			var data struct {
				SocketID string `json:"socket_id"`
			}
			if err := unmarshalStringData(frame.Data, &data); err != nil {
				return fmt.Errorf("parse connection_established: %w", err)
			}

			auth, err := p.api.authorizeChannel(channelName, data.SocketID)
			if err != nil {
				return fmt.Errorf("authorize channel: %w", err)
			}

			if err := p.send(pusherFrame{
				Event: "pusher:subscribe",
				Data:  mustMarshal(map[string]string{"channel": channelName, "auth": auth}),
			}); err != nil {
				return fmt.Errorf("subscribe: %w", err)
			}

		case "pusher:ping":
			if err := p.send(pusherFrame{Event: "pusher:pong"}); err != nil {
				return fmt.Errorf("pong: %w", err)
			}

		case "pusher_internal:subscription_succeeded":
			log.Printf("subscribed to %s — ready for jobs", channelName)

		case "pusher:error":
			log.Printf("reverb error frame: %s", string(frame.Data))

		case "job.assigned":
			var payload jobAssignedPayload
			if err := unmarshalStringData(frame.Data, &payload); err != nil {
				log.Printf("bad job.assigned payload: %v", err)
				continue
			}
			go p.onJob(payload)

		case "job.cancel":
			var payload jobCancelPayload
			if err := unmarshalStringData(frame.Data, &payload); err != nil {
				log.Printf("bad job.cancel payload: %v", err)
				continue
			}
			go p.onStop(payload)
		}
	}
}

func (p *pusherClient) send(frame pusherFrame) error {
	return p.conn.WriteJSON(frame)
}

// unmarshalStringData handles the Pusher protocol quirk where incoming
// `data` is itself a JSON-encoded string, not a raw object.
func unmarshalStringData(raw json.RawMessage, out any) error {
	var s string
	if err := json.Unmarshal(raw, &s); err != nil {
		// Some frames (e.g. bare pings) may already carry a plain object.
		return json.Unmarshal(raw, out)
	}

	if strings.TrimSpace(s) == "" {
		return nil
	}

	return json.Unmarshal([]byte(s), out)
}

func mustMarshal(v any) json.RawMessage {
	data, err := json.Marshal(v)
	if err != nil {
		panic(err)
	}
	return data
}

// runWithReconnect keeps the Reverb connection alive, reconnecting with a
// short backoff on any error (network blip, server restart, etc.).
func (p *pusherClient) runWithReconnect() {
	backoff := 2 * time.Second

	for {
		if err := p.connectAndListen(); err != nil {
			log.Printf("reverb connection lost: %v — reconnecting in %s", err, backoff)
		}

		time.Sleep(backoff)
		if backoff < 30*time.Second {
			backoff *= 2
		}
	}
}

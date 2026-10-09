package realtime

import (
	"encoding/json"
	"sync"
	"time"

	"github.com/coder/websocket"
)

type Event struct {
	Event     string    `json:"event"`
	Message   string    `json:"message"`
	IssueID   int64     `json:"issue_id,omitempty"`
	ActorID   int64     `json:"actor_id,omitempty"`
	Timestamp time.Time `json:"timestamp"`
}

type client struct {
	conn    *websocket.Conn
	issueID int64
	send    chan Event
}

type Hub struct {
	mu      sync.RWMutex
	clients map[*client]struct{}
}

func NewHub() *Hub {
	return &Hub{clients: map[*client]struct{}{}}
}

func (h *Hub) register(c *client) {
	h.mu.Lock()
	h.clients[c] = struct{}{}
	h.mu.Unlock()
}

func (h *Hub) unregister(c *client) {
	h.mu.Lock()
	if _, ok := h.clients[c]; ok {
		delete(h.clients, c)
		close(c.send)
	}
	h.mu.Unlock()
}

func (h *Hub) Publish(ev Event) {
	if ev.Timestamp.IsZero() {
		ev.Timestamp = time.Now().UTC()
	}
	data, err := json.Marshal(ev)
	if err != nil {
		return
	}
	h.mu.RLock()
	defer h.mu.RUnlock()
	for c := range h.clients {
		if ev.IssueID != 0 && c.issueID != 0 && c.issueID != ev.IssueID {
			continue
		}
		select {
		case c.send <- ev:
		default:
		}
		if c.conn != nil {
			go func(conn *websocket.Conn, payload []byte) {
				_ = conn.Write(nil, websocket.MessageText, payload)
			}(c.conn, data)
		}
	}
}

func (h *Hub) Handle(issueID int64, conn *websocket.Conn) {
	c := &client{conn: conn, issueID: issueID, send: make(chan Event, 16)}
	h.register(c)
	defer func() {
		h.unregister(c)
		_ = conn.Close(websocket.StatusNormalClosure, "bye")
	}()
	go func() {
		for ev := range c.send {
			data, err := json.Marshal(ev)
			if err != nil {
				continue
			}
			_ = conn.Write(nil, websocket.MessageText, data)
		}
	}()
	for {
		_, _, err := conn.Read(nil)
		if err != nil {
			return
		}
	}
}
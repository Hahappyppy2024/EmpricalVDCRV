// Package service contains the real-time event bus and the webhook delivery
// adapter (deterministic local HTTP delivery).
package service

import (
	"encoding/json"
	"sync"
)

// EventBus fans out real-time issue events to WebSocket clients per project.
type EventBus struct {
	mu      sync.RWMutex
	clients map[int64]map[*Client]bool
}

// Client is a connected WebSocket subscriber.
type Client struct {
	Send chan []byte
	done chan struct{}
}

// NewEventBus creates an empty bus.
func NewEventBus() *EventBus {
	return &EventBus{clients: make(map[int64]map[*Client]bool)}
}

// Subscribe registers a client for a project room.
func (b *EventBus) Subscribe(projectID int64, c *Client) {
	b.mu.Lock()
	defer b.mu.Unlock()
	if c.done == nil {
		c.done = make(chan struct{})
	}
	if b.clients[projectID] == nil {
		b.clients[projectID] = make(map[*Client]bool)
	}
	b.clients[projectID][c] = true
}

// Unsubscribe removes a client from a project room.
func (b *EventBus) Unsubscribe(projectID int64, c *Client) {
	b.mu.Lock()
	defer b.mu.Unlock()
	if set, ok := b.clients[projectID]; ok {
		delete(set, c)
		if len(set) == 0 {
			delete(b.clients, projectID)
		}
	}
	close(c.done)
}

// Done returns a channel closed when the client is unsubscribed.
func (c *Client) Done() <-chan struct{} { return c.done }

// Publish sends an event message to every subscriber of a project room.
func (b *EventBus) Publish(projectID int64, event string, data any) {
	b.mu.RLock()
	defer b.mu.RUnlock()
	msg, _ := json.Marshal(map[string]any{
		"event": event,
		"data":  data,
		"at":    nowRFC3339(),
	})
	for c := range b.clients[projectID] {
		select {
		case c.Send <- msg:
		default:
		}
	}
}

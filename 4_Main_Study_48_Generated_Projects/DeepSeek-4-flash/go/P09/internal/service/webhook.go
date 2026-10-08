package service

import (
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"

	"issuetracker/internal/models"
	"issuetracker/internal/store"
)

func nowRFC3339() string { return time.Now().UTC().Format("2006-01-02T15:04:05Z07:00") }

// WebhookService delivers outbound webhook events with a deterministic local
// adapter: each event is POSTed over HTTP with an HMAC-SHA256 signature, and
// the attempt is recorded as a WebhookDelivery row (delivered/failed).
type WebhookService struct {
	Store    *store.Store
	Client   *http.Client
	Retries  int
	Listener string // local listener path, used for seed URLs
}

// NewWebhookService builds the service with a short-timeout HTTP client so the
// application stays responsive offline.
func NewWebhookService(s *store.Store, retries int) *WebhookService {
	return &WebhookService{
		Store:   s,
		Client:  &http.Client{Timeout: 5 * time.Second},
		Retries: retries,
	}
}

// IssueEventPayload is the JSON body delivered to webhook endpoints.
type IssueEventPayload struct {
	Event     string `json:"event"`
	IssueID   int64  `json:"issue_id"`
	ProjectID int64  `json:"project_id"`
	Project   string `json:"project"`
	Number    int    `json:"number"`
	Title     string `json:"title"`
	Status    string `json:"status"`
	Priority  string `json:"priority"`
	Actor     string `json:"actor"`
	At        string `json:"at"`
}

// NotifyIssueEvent enqueues delivery of an issue event to all subscribed
// active webhooks of the project. Delivery runs synchronously in a short
// goroutine so the request flow is not blocked.
func (w *WebhookService) NotifyIssueEvent(issue *models.Issue, actorName, event string) {
	payload := IssueEventPayload{
		Event:     event,
		IssueID:   issue.ID,
		ProjectID: issue.ProjectID,
		Project:   issue.ProjectSlug,
		Number:    issue.Number,
		Title:     issue.Title,
		Status:    issue.Status,
		Priority:  issue.Priority,
		Actor:     actorName,
		At:        nowRFC3339(),
	}
	hooks, err := w.Store.ActiveWebhooksFor(issue.ProjectID, event)
	if err != nil || len(hooks) == 0 {
		return
	}
	body, _ := json.Marshal(payload)
	for _, h := range hooks {
		hook := h
		go w.deliver(hook, event, body)
	}
}

// deliver performs one deterministic delivery attempt sequence.
func (w *WebhookService) deliver(hook models.Webhook, event string, body []byte) {
	status, response := w.tryDeliver(hook, body, 1)
	attempts := 1
	retries := w.Retries
	if retries <= 0 {
		retries = 3
	}
	for status == "failed" && attempts < retries {
		attempts++
		status, response = w.tryDeliver(hook, body, attempts)
	}
	_, _ = w.Store.CreateDelivery(hook.ID, event, string(body), status, truncate(response, 2000), attempts)
}

// tryDeliver POSTs the signed payload once and returns status + response text.
func (w *WebhookService) tryDeliver(hook models.Webhook, body []byte, attempt int) (string, string) {
	req, err := http.NewRequest(http.MethodPost, hook.URL, bytes.NewReader(body))
	if err != nil {
		return "failed", fmt.Sprintf("invalid webhook URL: %v", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Issue-Tracker-Event", hook.Events[0])
	sig := hmac.New(sha256.New, []byte(hook.Secret))
	sig.Write(body)
	req.Header.Set("X-Issue-Tracker-Signature", "sha256="+hex.EncodeToString(sig.Sum(nil)))
	req.Header.Set("X-Issue-Tracker-Attempt", fmt.Sprint(attempt))
	resp, err := w.Client.Do(req)
	if err != nil {
		return "failed", fmt.Sprintf("connection error: %v", err)
	}
	defer resp.Body.Close()
	rb, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
	if resp.StatusCode >= 200 && resp.StatusCode < 300 {
		return "delivered", fmt.Sprintf("HTTP %d %s", resp.StatusCode, truncate(string(rb), 500))
	}
	return "failed", fmt.Sprintf("HTTP %d %s", resp.StatusCode, truncate(string(rb), 500))
}

func truncate(s string, n int) string {
	if len(s) <= n {
		return s
	}
	return strings.TrimSpace(s[:n]) + "..."
}

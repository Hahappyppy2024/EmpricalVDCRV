package webhooks

import (
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"database/sql"
	"encoding/hex"
	"errors"
	"fmt"
	"net/http"
	"net/http/httptest"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/go-chi/chi/v5"

	"github.com/anomaly/p09/internal/auth"
	"github.com/anomaly/p09/internal/httpx"
	"github.com/anomaly/p09/internal/models"
	"github.com/anomaly/p09/internal/realtime"
	"github.com/anomaly/p09/internal/templates"
)

type Service struct {
	DB  *sql.DB
	Hub *realtime.Hub
}

func NewService(db *sql.DB, hub *realtime.Hub) *Service {
	return &Service{DB: db, Hub: hub}
}

type Webhook struct {
	models.Webhook
	ProjectSlug string `json:"project_slug"`
}

type Delivery struct {
	models.WebhookDelivery
}

type Handler struct {
	Service *Service
}

func NewHandler(s *Service) *Handler { return &Handler{Service: s} }

func (h *Handler) Routes(r chi.Router, tpl *templates.Engine) {
	r.Get("/api/issue/webhooks", h.list)
	r.Post("/api/issue/webhooks/{projectID}", h.create)
	r.Post("/api/issue/webhooks/{id}/toggle", h.toggle)
	r.Post("/api/issue/webhooks/{id}/delete", h.delete)
	r.Get("/webhooks/{projectID}", func(w http.ResponseWriter, req *http.Request) { h.Page(w, req, tpl) })
}

func (h *Handler) list(w http.ResponseWriter, r *http.Request) {
	rows, err := h.Service.DB.QueryContext(r.Context(), `SELECT w.id, w.project_id, p.slug, w.url, w.secret, w.events, w.active FROM webhooks w JOIN projects p ON p.id=w.project_id ORDER BY w.id DESC`)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	defer rows.Close()
	var out []Webhook
	for rows.Next() {
		var wh Webhook
		var active int
		if err := rows.Scan(&wh.ID, &wh.ProjectID, &wh.ProjectSlug, &wh.URL, &wh.Secret, &wh.Events, &active); err != nil {
			httpx.WriteError(w, err)
			return
		}
		wh.Active = active != 0
		out = append(out, wh)
	}
	httpx.WriteJSON(w, http.StatusOK, out)
}

type createPayload struct {
	URL    string `json:"url"`
	Secret string `json:"secret"`
	Events string `json:"events"`
}

func (h *Handler) create(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		httpx.WriteError(w, httpx.Unauthorized("authentication required"))
		return
	}
	projectID, err := strconv.ParseInt(chi.URLParam(r, "projectID"), 10, 64)
	if err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid project id"))
		return
	}
	if !canManageWebhook(r.Context(), h.Service.DB, u, projectID) {
		httpx.WriteError(w, httpx.Forbidden("not allowed"))
		return
	}
	var in createPayload
	if err := r.ParseForm(); err == nil {
		in.URL = strings.TrimSpace(r.FormValue("url"))
		in.Secret = strings.TrimSpace(r.FormValue("secret"))
		in.Events = r.FormValue("events")
	} else if err := httpx.ReadJSON(r, &in); err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid input"))
		return
	}
	if !strings.HasPrefix(in.URL, "http://") && !strings.HasPrefix(in.URL, "https://") {
		httpx.WriteError(w, httpx.BadRequest("webhook URL must be http(s)"))
		return
	}
	if in.Events == "" {
		in.Events = "issue.opened,issue.closed,issue.commented"
	}
	res, err := h.Service.DB.ExecContext(r.Context(), `INSERT INTO webhooks(project_id, url, secret, events) VALUES (?,?,?,?)`, projectID, in.URL, in.Secret, in.Events)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	id, _ := res.LastInsertId()
	_, _ = h.Service.DB.ExecContext(r.Context(), `INSERT INTO audit_events(actor_id, action, target_type, target_id, detail) VALUES (?, 'webhook.create', 'webhook', ?, ?)`, u.ID, id, in.URL)
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/webhooks/"+strconv.FormatInt(projectID, 10), http.StatusSeeOther)
		return
	}
	httpx.WriteJSON(w, http.StatusCreated, map[string]any{"id": id, "url": in.URL})
}

func (h *Handler) toggle(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		httpx.WriteError(w, httpx.Unauthorized("authentication required"))
		return
	}
	id, err := strconv.ParseInt(chi.URLParam(r, "id"), 10, 64)
	if err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid id"))
		return
	}
	var projectID int64
	_ = h.Service.DB.QueryRowContext(r.Context(), `SELECT project_id FROM webhooks WHERE id=?`, id).Scan(&projectID)
	if !canManageWebhook(r.Context(), h.Service.DB, u, projectID) {
		httpx.WriteError(w, httpx.Forbidden("not allowed"))
		return
	}
	_, err = h.Service.DB.ExecContext(r.Context(), `UPDATE webhooks SET active = 1 - active WHERE id=?`, id)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/webhooks/"+strconv.FormatInt(projectID, 10), http.StatusSeeOther)
		return
	}
	httpx.WriteMessage(w, http.StatusOK, "toggled")
}

func (h *Handler) delete(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		httpx.WriteError(w, httpx.Unauthorized("authentication required"))
		return
	}
	id, err := strconv.ParseInt(chi.URLParam(r, "id"), 10, 64)
	if err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid id"))
		return
	}
	var projectID int64
	_ = h.Service.DB.QueryRowContext(r.Context(), `SELECT project_id FROM webhooks WHERE id=?`, id).Scan(&projectID)
	if !canManageWebhook(r.Context(), h.Service.DB, u, projectID) {
		httpx.WriteError(w, httpx.Forbidden("not allowed"))
		return
	}
	if _, err := h.Service.DB.ExecContext(r.Context(), `DELETE FROM webhooks WHERE id=?`, id); err != nil {
		httpx.WriteError(w, err)
		return
	}
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/webhooks/"+strconv.FormatInt(projectID, 10), http.StatusSeeOther)
		return
	}
	httpx.WriteMessage(w, http.StatusOK, "deleted")
}

func canManageWebhook(ctx context.Context, db *sql.DB, u *models.User, projectID int64) bool {
	if u == nil {
		return false
	}
	if u.Role == models.RoleAdmin {
		return true
	}
	var ownerID int64
	_ = db.QueryRowContext(ctx, `SELECT owner_id FROM projects WHERE id=?`, projectID).Scan(&ownerID)
	if ownerID == u.ID {
		return true
	}
	var role string
	_ = db.QueryRowContext(ctx, `SELECT role FROM project_members WHERE project_id=? AND user_id=?`, projectID, u.ID).Scan(&role)
	return role == "maintainer"
}

var ErrNoWebhook = errors.New("no webhook")

func Dispatch(ctx context.Context, db *sql.DB, hub *realtime.Hub, eventName, payload string, projectID, actorID int64) {
	if hub != nil {
		hub.Publish(realtime.Event{Event: eventName, Message: payload, IssueID: 0, ActorID: actorID})
	}
	rows, err := db.QueryContext(ctx, `SELECT id, url, secret, events, active FROM webhooks WHERE project_id=?`, projectID)
	if err != nil {
		return
	}
	defer rows.Close()
	type entry struct {
		ID     int64
		URL    string
		Secret string
		Events string
		Active bool
	}
	var entries []entry
	for rows.Next() {
		var e entry
		var active int
		if err := rows.Scan(&e.ID, &e.URL, &e.Secret, &e.Events, &active); err != nil {
			continue
		}
		e.Active = active != 0
		if !e.Active {
			continue
		}
		if !matchEvent(e.Events, eventName) {
			continue
		}
		entries = append(entries, e)
	}
	for _, e := range entries {
		go deliver(db, e, eventName, payload)
	}
}

func matchEvent(list, event string) bool {
	if list == "" || list == "*" {
		return true
	}
	for _, e := range strings.Split(list, ",") {
		if strings.TrimSpace(e) == event {
			return true
		}
	}
	return false
}

var deliveryClient = &http.Client{Timeout: 10 * time.Second}

func deliver(db *sql.DB, entry struct {
	ID     int64
	URL    string
	Secret string
	Events string
	Active bool
}, event, payload string) {
	body := payload
	if entry.Secret != "" {
		mac := hmac.New(sha256.New, []byte(entry.Secret))
		mac.Write([]byte(body))
		signature := hex.EncodeToString(mac.Sum(nil))
		body = body + "\n--signature: " + signature
	}
	req, err := http.NewRequest("POST", entry.URL, strings.NewReader(body))
	if err != nil {
		recordDelivery(db, entry.ID, event, payload, "failed", 0, err.Error())
		return
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Issue-Tracker-Event", event)
	if entry.Secret != "" {
		mac := hmac.New(sha256.New, []byte(entry.Secret))
		mac.Write([]byte(payload))
		req.Header.Set("X-Issue-Tracker-Signature", hex.EncodeToString(mac.Sum(nil)))
	}
	resp, err := deliveryClient.Do(req)
	if err != nil {
		recordDelivery(db, entry.ID, event, payload, "failed", 0, err.Error())
		return
	}
	defer resp.Body.Close()
	buf := make([]byte, 1024)
	n, _ := resp.Body.Read(buf)
	body2 := strings.TrimSpace(string(buf[:n]))
	status := "success"
	if resp.StatusCode >= 400 {
		status = "failed"
	}
	recordDelivery(db, entry.ID, event, payload, status, resp.StatusCode, body2)
}

func recordDelivery(db *sql.DB, webhookID int64, event, payload, status string, code int, response string) {
	_, _ = db.Exec(`INSERT INTO webhook_deliveries(webhook_id, event, payload, status, response_code, response_body) VALUES (?,?,?,?,?,?)`, webhookID, event, payload, status, code, truncate(response, 512))
}

func truncate(s string, n int) string {
	if len(s) <= n {
		return s
	}
	return s[:n] + "..."
}

type dispatchOnce struct {
	mu  sync.Mutex
	cfg []httptest.Server
}

var _ = dispatchOnce{}

func LookupDeliveries(ctx context.Context, db *sql.DB, projectID int64) ([]Delivery, error) {
	rows, err := db.QueryContext(ctx, `SELECT wd.id, wd.webhook_id, wd.event, wd.payload, wd.status, wd.response_code, wd.response_body, wd.created_at FROM webhook_deliveries wd JOIN webhooks w ON w.id=wd.webhook_id WHERE w.project_id=? ORDER BY wd.id DESC LIMIT 50`, projectID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []Delivery
	for rows.Next() {
		var d Delivery
		if err := rows.Scan(&d.ID, &d.WebhookID, &d.Event, &d.Payload, &d.Status, &d.ResponseCode, &d.ResponseBody, &d.CreatedAt); err != nil {
			return nil, err
		}
		out = append(out, d)
	}
	return out, rows.Err()
}

func ListProjectWebhooks(ctx context.Context, db *sql.DB, projectID int64) ([]Webhook, error) {
	rows, err := db.QueryContext(ctx, `SELECT id, project_id, p.slug, url, secret, events, active FROM webhooks w JOIN projects p ON p.id=w.project_id WHERE project_id=? ORDER BY w.id DESC`, projectID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []Webhook
	for rows.Next() {
		var w Webhook
		var active int
		if err := rows.Scan(&w.ID, &w.ProjectID, &w.ProjectSlug, &w.URL, &w.Secret, &w.Events, &active); err != nil {
			return nil, err
		}
		w.Active = active != 0
		out = append(out, w)
	}
	return out, rows.Err()
}

var _ = fmt.Sprintf
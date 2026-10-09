package frontend_errors

import (
	"context"
	"database/sql"
	"encoding/json"
	"net/http"
	"strings"

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

type ErrReport struct {
	models.FrontendError
	Username  string `json:"username,omitempty"`
	CreatedAt string `json:"created_at"`
}

type Handler struct {
	Service *Service
}

func NewHandler(s *Service) *Handler { return &Handler{Service: s} }

func (h *Handler) Routes(r chi.Router, tpl *templates.Engine) {
	r.Get("/api/issue/frontend_api_integration_and_errors", h.list)
	r.Post("/api/issue/frontend_api_integration_and_errors", h.report)
	r.Get("/frontend-errors", func(w http.ResponseWriter, req *http.Request) { h.Page(w, req, tpl) })
}

type reportPayload struct {
	Kind    string `json:"kind"`
	Message string `json:"message"`
	Context string `json:"context"`
}

func (h *Handler) list(w http.ResponseWriter, r *http.Request) {
	rows, err := h.Service.DB.QueryContext(r.Context(), `SELECT e.id, e.user_id, e.kind, e.message, e.context, e.created_at, COALESCE(u.username, '') FROM frontend_errors e LEFT JOIN users u ON u.id=e.user_id ORDER BY e.id DESC LIMIT 100`)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	defer rows.Close()
	var out []ErrReport
	for rows.Next() {
		var e ErrReport
		if err := rows.Scan(&e.ID, &e.UserID, &e.Kind, &e.Message, &e.Context, &e.CreatedAt, &e.Username); err != nil {
			httpx.WriteError(w, err)
			return
		}
		out = append(out, e)
	}
	httpx.WriteJSON(w, http.StatusOK, out)
}

func (h *Handler) report(w http.ResponseWriter, r *http.Request) {
	u, _ := auth.UserFromContext(r.Context())
	var in reportPayload
	if err := r.ParseForm(); err == nil {
		in.Kind = strings.TrimSpace(r.FormValue("kind"))
		in.Message = strings.TrimSpace(r.FormValue("message"))
		in.Context = strings.TrimSpace(r.FormValue("context"))
	} else {
		if err := json.NewDecoder(r.Body).Decode(&in); err != nil {
			httpx.WriteError(w, httpx.BadRequest("invalid input"))
			return
		}
	}
	if in.Kind == "" {
		in.Kind = "javascript"
	}
	if in.Message == "" {
		httpx.WriteError(w, httpx.BadRequest("message required"))
		return
	}
	if !validKind(in.Kind) {
		httpx.WriteError(w, httpx.BadRequest("invalid kind"))
		return
	}
	var uid any
	if u != nil {
		uid = u.ID
	}
	res, err := h.Service.DB.ExecContext(r.Context(), `INSERT INTO frontend_errors(user_id, kind, message, context) VALUES (?,?,?,?)`, uid, in.Kind, in.Message, in.Context)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	id, _ := res.LastInsertId()
	if h.Service.Hub != nil {
		h.Service.Hub.Publish(realtime.Event{Event: "frontend.error", Message: in.Message, IssueID: 0, ActorID: anyInt64(uid)})
	}
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/frontend-errors", http.StatusSeeOther)
		return
	}
	httpx.WriteJSON(w, http.StatusCreated, map[string]any{"id": id})
}

func validKind(kind string) bool {
	switch kind {
	case "validation", "permission", "webhook", "upload", "network", "javascript", "promise":
		return true
	}
	return false
}

func anyInt64(v any) int64 {
	if id, ok := v.(int64); ok {
		return id
	}
	return 0
}

func (h *Handler) Page(w http.ResponseWriter, r *http.Request, tpl *templates.Engine) {
	u, _ := auth.UserFromContext(r.Context())
	rows, err := h.Service.DB.QueryContext(r.Context(), `SELECT id, user_id, kind, message, context, created_at FROM frontend_errors ORDER BY id DESC LIMIT 50`)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	defer rows.Close()
	var errs []ErrReport
	for rows.Next() {
		var e ErrReport
		if err := rows.Scan(&e.ID, &e.UserID, &e.Kind, &e.Message, &e.Context, &e.CreatedAt); err != nil {
			continue
		}
		errs = append(errs, e)
	}
	tpl.Render(w, "frontend_errors.html", map[string]any{
		"User":      u,
		"Errors":    errs,
		"CSRFToken": auth.CSRFToken(r.Context()),
	})
}

var _ = context.Background
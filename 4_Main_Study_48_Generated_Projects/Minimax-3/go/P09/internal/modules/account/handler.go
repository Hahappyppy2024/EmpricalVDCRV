package account

import (
	"context"
	"database/sql"
	"net/http"
	"strconv"
	"strings"

	"github.com/go-chi/chi/v5"

	"github.com/anomaly/p09/internal/auth"
	"github.com/anomaly/p09/internal/httpx"
	"github.com/anomaly/p09/internal/models"
	"github.com/anomaly/p09/internal/templates"
)

type Handler struct {
	db   *sql.DB
	auth *auth.Service
}

func NewHandler(db *sql.DB, a *auth.Service) *Handler {
	return &Handler{db: db, auth: a}
}

func (h *Handler) Routes(r chi.Router, tpl *templates.Engine) {
	r.Get("/api/issue/account_access", h.list)
	r.Post("/api/issue/account_access/register", h.register)
	r.Post("/api/issue/account_access/login", h.login)
	r.Post("/api/issue/account_access/logout", h.logout)
	r.Post("/api/issue/account_access/profile", h.profile)
	r.Post("/api/issue/account_access/revoke/{id}", h.revoke)
	r.Get("/account", func(w http.ResponseWriter, req *http.Request) { h.Page(w, req, tpl) })
	r.Get("/account/access", func(w http.ResponseWriter, req *http.Request) { h.SessionsPage(w, req, tpl) })
}

type accessEvent struct {
	ID        int64  `json:"id"`
	Action    string `json:"action"`
	Detail    string `json:"detail"`
	CreatedAt string `json:"created_at"`
}

func (h *Handler) recordEvent(ctx context.Context, userID int64, action, detail string) {
	_, _ = h.db.ExecContext(ctx, `INSERT INTO account_access(user_id, action, detail) VALUES (?,?,?)`, userID, action, detail)
}

func (h *Handler) list(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		httpx.WriteError(w, httpx.Unauthorized("authentication required"))
		return
	}
	httpx.WriteJSON(w, http.StatusOK, map[string]any{
		"user":  u,
		"ready": true,
	})
}

type registerPayload struct {
	Username string `json:"username"`
	Email    string `json:"email"`
	FullName string `json:"full_name"`
	Password string `json:"password"`
	Role     string `json:"role"`
}

func (h *Handler) register(w http.ResponseWriter, r *http.Request) {
	var in registerPayload
	if err := r.ParseForm(); err == nil {
		in.Username = strings.TrimSpace(r.FormValue("username"))
		in.Email = strings.TrimSpace(r.FormValue("email"))
		in.FullName = strings.TrimSpace(r.FormValue("full_name"))
		in.Password = r.FormValue("password")
		in.Role = r.FormValue("role")
	} else {
		if err := httpx.ReadJSON(r, &in); err != nil {
			httpx.WriteError(w, httpx.BadRequest("invalid JSON"))
			return
		}
	}
	u, err := h.auth.Register(r.Context(), auth.RegisterInput{
		Username: in.Username,
		Email:    in.Email,
		FullName: in.FullName,
		Password: in.Password,
		Role:     in.Role,
	})
	if err != nil {
		switch err {
		case auth.ErrUserExists:
			httpx.WriteError(w, httpx.Conflict("username or email already exists"))
		case auth.ErrWeakPassword:
			httpx.WriteError(w, httpx.Validation("password must be at least 8 characters"))
		default:
			httpx.WriteError(w, httpx.BadRequest(err.Error()))
		}
		return
	}
	h.recordEvent(r.Context(), u.ID, "register", "self-registration via API")
	if isHTMX(r) || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/login", http.StatusSeeOther)
		return
	}
	httpx.WriteJSON(w, http.StatusCreated, u)
}

type loginPayload struct {
	Username string `json:"username"`
	Password string `json:"password"`
	Next     string `json:"next"`
}

func (h *Handler) login(w http.ResponseWriter, r *http.Request) {
	var in loginPayload
	if err := r.ParseForm(); err == nil {
		in.Username = strings.TrimSpace(r.FormValue("username"))
		in.Password = r.FormValue("password")
		in.Next = r.FormValue("next")
	} else {
		if err := httpx.ReadJSON(r, &in); err != nil {
			httpx.WriteError(w, httpx.BadRequest("invalid JSON"))
			return
		}
	}
	u, err := h.auth.Authenticate(r.Context(), in.Username, in.Password)
	if err != nil {
		h.recordEvent(r.Context(), 0, "login_failed", in.Username)
		if isHTMX(r) || r.Header.Get("Accept") == "text/html" {
			http.Redirect(w, r, "/login?error=invalid", http.StatusSeeOther)
			return
		}
		httpx.WriteError(w, httpx.Unauthorized("invalid credentials"))
		return
	}
	sess, err := h.auth.CreateSession(r.Context(), u.ID, auth.ClientIP(r), r.UserAgent())
	if err != nil {
		httpx.WriteError(w, httpx.ServerError("could not create session"))
		return
	}
	h.auth.WriteCookie(w, sess, false)
	h.recordEvent(r.Context(), u.ID, "login", "session created")
	if isHTMX(r) || r.Header.Get("Accept") == "text/html" {
		redir := in.Next
		if redir == "" || redir[0] != '/' {
			redir = "/"
		}
		http.Redirect(w, r, redir, http.StatusSeeOther)
		return
	}
	httpx.WriteJSON(w, http.StatusOK, map[string]any{"user": u, "csrf": sess.CSRFToken})
}

func (h *Handler) logout(w http.ResponseWriter, r *http.Request) {
	if sess, ok := auth.SessionFromContext(r.Context()); ok {
		_ = h.auth.DeleteSession(r.Context(), sess.ID)
		if u, ok := auth.UserFromContext(r.Context()); ok {
			h.recordEvent(r.Context(), u.ID, "logout", "session ended")
		}
	}
	h.auth.ClearCookie(w)
	http.Redirect(w, r, "/", http.StatusSeeOther)
}

type profilePayload struct {
	FullName string `json:"full_name"`
	Bio      string `json:"bio"`
}

func (h *Handler) profile(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		httpx.WriteError(w, httpx.Unauthorized("authentication required"))
		return
	}
	var in profilePayload
	if err := r.ParseForm(); err == nil {
		in.FullName = strings.TrimSpace(r.FormValue("full_name"))
		in.Bio = strings.TrimSpace(r.FormValue("bio"))
	} else if err := httpx.ReadJSON(r, &in); err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid input"))
		return
	}
	if err := h.auth.UpdateProfile(r.Context(), u.ID, in.FullName, in.Bio); err != nil {
		httpx.WriteError(w, httpx.ServerError(err.Error()))
		return
	}
	h.recordEvent(r.Context(), u.ID, "profile_update", "name/bio updated")
	http.Redirect(w, r, "/account", http.StatusSeeOther)
}

func (h *Handler) revoke(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")
	sid, err := strconv.ParseInt(id, 10, 64)
	if err != nil || sid <= 0 {
		httpx.WriteError(w, httpx.BadRequest("invalid session id"))
		return
	}
	row := h.db.QueryRowContext(r.Context(), `SELECT id FROM sessions WHERE id=?`, strconv.FormatInt(sid, 10))
	var dummy string
	if err := row.Scan(&dummy); err != nil {
		httpx.WriteError(w, httpx.NotFound("session not found"))
		return
	}
	if err := h.auth.DeleteSession(r.Context(), strconv.FormatInt(sid, 10)); err != nil {
		httpx.WriteError(w, httpx.ServerError(err.Error()))
		return
	}
	if u, ok := auth.UserFromContext(r.Context()); ok {
		h.recordEvent(r.Context(), u.ID, "session_revoke", strconv.FormatInt(sid, 10))
	}
	http.Redirect(w, r, "/account/access", http.StatusSeeOther)
}

type sessionInfo struct {
	ID        string `json:"id"`
	CreatedAt string `json:"created_at"`
	ExpiresAt string `json:"expires_at"`
	IP        string `json:"ip"`
	UA        string `json:"user_agent"`
}

func (h *Handler) listSessions(ctx context.Context, userID int64) ([]sessionInfo, error) {
	rows, err := h.db.QueryContext(ctx, `SELECT id, created_at, expires_at, ip, user_agent FROM sessions WHERE user_id=? ORDER BY created_at DESC`, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []sessionInfo
	for rows.Next() {
		s := sessionInfo{}
		if err := rows.Scan(&s.ID, &s.CreatedAt, &s.ExpiresAt, &s.IP, &s.UA); err != nil {
			return nil, err
		}
		out = append(out, s)
	}
	return out, rows.Err()
}

func (h *Handler) recentEvents(ctx context.Context, userID int64) ([]accessEvent, error) {
	rows, err := h.db.QueryContext(ctx, `SELECT id, action, detail, created_at FROM account_access WHERE user_id=? ORDER BY id DESC LIMIT 25`, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []accessEvent
	for rows.Next() {
		e := accessEvent{}
		if err := rows.Scan(&e.ID, &e.Action, &e.Detail, &e.CreatedAt); err != nil {
			return nil, err
		}
		out = append(out, e)
	}
	return out, rows.Err()
}

type seedAccount struct {
	Username string `json:"username"`
	Password string `json:"password"`
	Role     string `json:"role"`
}

var defaultSeedAccounts = []seedAccount{
	{Username: "alice", Password: "alicepass1", Role: models.RoleAdmin},
	{Username: "bob", Password: "bobpass123", Role: models.RoleMaintainer},
	{Username: "carol", Password: "carolpass1", Role: models.RoleDeveloper},
	{Username: "dave", Password: "davepass1", Role: models.RoleDeveloper},
	{Username: "erin", Password: "erinpass1", Role: models.RoleReporter},
}

func isHTMX(r *http.Request) bool {
	return r.Header.Get("HX-Request") == "true"
}
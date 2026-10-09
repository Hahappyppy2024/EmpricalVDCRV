package admin

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

type Service struct {
	DB *sql.DB
}

func NewService(db *sql.DB) *Service { return &Service{DB: db} }

type Handler struct {
	Service *Service
}

func NewHandler(s *Service) *Handler { return &Handler{Service: s} }

func (h *Handler) Routes(r chi.Router, tpl *templates.Engine) {
	r.Get("/api/issue/admin_operations", h.list)
	r.Post("/api/issue/admin_operations/users/{id}/role", h.setRole)
	r.Post("/api/issue/admin_operations/users/{id}/status", h.setStatus)
	r.Post("/api/issue/admin_operations/settings", h.saveSettings)
	r.Post("/api/issue/admin_operations/transfer", h.transfer)
}

type adminOp struct {
	ID         int64  `json:"id"`
	Action     string `json:"action"`
	Detail     string `json:"detail"`
	TargetType string `json:"target_type"`
	TargetID   *int64 `json:"target_id"`
	CreatedAt  string `json:"created_at"`
	Actor      string `json:"actor_username"`
}

func (h *Handler) list(w http.ResponseWriter, r *http.Request) {
	rows, err := h.Service.DB.QueryContext(r.Context(), `SELECT a.id, a.action, a.detail, a.target_type, a.target_id, a.created_at, COALESCE(u.username, '') FROM audit_events a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 50`)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	defer rows.Close()
	var out []adminOp
	for rows.Next() {
		var op adminOp
		if err := rows.Scan(&op.ID, &op.Action, &op.Detail, &op.TargetType, &op.TargetID, &op.CreatedAt, &op.Actor); err != nil {
			httpx.WriteError(w, err)
			return
		}
		out = append(out, op)
	}
	httpx.WriteJSON(w, http.StatusOK, out)
}

type rolePayload struct {
	Role string `json:"role"`
}

func (h *Handler) setRole(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok || u.Role != models.RoleAdmin {
		httpx.WriteError(w, httpx.Forbidden("admin only"))
		return
	}
	id, err := strconv.ParseInt(chi.URLParam(r, "id"), 10, 64)
	if err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid id"))
		return
	}
	role := strings.TrimSpace(r.FormValue("role"))
	if err := setRoleTx(r.Context(), h.Service.DB, u.ID, id, role); err != nil {
		httpx.WriteError(w, err)
		return
	}
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/admin", http.StatusSeeOther)
		return
	}
	httpx.WriteMessage(w, http.StatusOK, "role updated")
}

type statusPayload struct {
	Status string `json:"status"`
}

func (h *Handler) setStatus(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok || u.Role != models.RoleAdmin {
		httpx.WriteError(w, httpx.Forbidden("admin only"))
		return
	}
	id, err := strconv.ParseInt(chi.URLParam(r, "id"), 10, 64)
	if err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid id"))
		return
	}
	status := strings.TrimSpace(r.FormValue("status"))
	if status != "active" && status != "suspended" {
		httpx.WriteError(w, httpx.BadRequest("invalid status"))
		return
	}
	if _, err := h.Service.DB.ExecContext(r.Context(), `UPDATE users SET status=?, updated_at=datetime('now') WHERE id=?`, status, id); err != nil {
		httpx.WriteError(w, err)
		return
	}
	if status == "suspended" {
		_, _ = h.Service.DB.ExecContext(r.Context(), `DELETE FROM sessions WHERE user_id=?`, id)
	}
	_, _ = h.Service.DB.ExecContext(r.Context(), `INSERT INTO audit_events(actor_id, action, target_type, target_id, detail) VALUES (?, 'user.status', 'user', ?, ?)`, u.ID, id, status)
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/admin", http.StatusSeeOther)
		return
	}
	httpx.WriteMessage(w, http.StatusOK, "status updated")
}

type settingsPayload struct {
	WorkspaceName    string `json:"workspace_name"`
	AllowRegistration string `json:"allow_registration"`
	DefaultPriority  string `json:"default_priority"`
}

func (h *Handler) saveSettings(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok || u.Role != models.RoleAdmin {
		httpx.WriteError(w, httpx.Forbidden("admin only"))
		return
	}
	if err := r.ParseForm(); err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid form"))
		return
	}
	pairs := map[string]string{
		"workspace_name":     r.FormValue("workspace_name"),
		"allow_registration": r.FormValue("allow_registration"),
		"default_priority":   r.FormValue("default_priority"),
	}
	for k, v := range pairs {
		if _, err := h.Service.DB.ExecContext(r.Context(), `INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=datetime('now')`, k, v); err != nil {
			httpx.WriteError(w, err)
			return
		}
	}
	_, _ = h.Service.DB.ExecContext(r.Context(), `INSERT INTO audit_events(actor_id, action, target_type, target_id, detail) VALUES (?, 'settings.update', 'settings', NULL, ?)`, u.ID, pairs["workspace_name"])
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/admin", http.StatusSeeOther)
		return
	}
	httpx.WriteMessage(w, http.StatusOK, "settings saved")
}

type transferPayload struct {
	ProjectID int64 `json:"project_id"`
	UserID    int64 `json:"user_id"`
}

func (h *Handler) transfer(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok || u.Role != models.RoleAdmin {
		httpx.WriteError(w, httpx.Forbidden("admin only"))
		return
	}
	if err := r.ParseForm(); err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid form"))
		return
	}
	pid, err := strconv.ParseInt(r.FormValue("project_id"), 10, 64)
	if err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid project_id"))
		return
	}
	uid, err := strconv.ParseInt(r.FormValue("user_id"), 10, 64)
	if err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid user_id"))
		return
	}
	tx, err := h.Service.DB.BeginTx(r.Context(), nil)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	defer tx.Rollback()
	if _, err := tx.ExecContext(r.Context(), `UPDATE projects SET owner_id=?, updated_at=datetime('now') WHERE id=?`, uid, pid); err != nil {
		httpx.WriteError(w, err)
		return
	}
	if _, err := tx.ExecContext(r.Context(), `INSERT OR IGNORE INTO project_members(project_id, user_id, role) VALUES (?,?, 'maintainer')`, pid, uid); err != nil {
		httpx.WriteError(w, err)
		return
	}
	if _, err := tx.ExecContext(r.Context(), `INSERT OR IGNORE INTO private_access(project_id, user_id, granted_by) VALUES (?,?,?)`, pid, uid, uid); err != nil {
		httpx.WriteError(w, err)
		return
	}
	if err := tx.Commit(); err != nil {
		httpx.WriteError(w, err)
		return
	}
	_, _ = h.Service.DB.ExecContext(r.Context(), `INSERT INTO audit_events(actor_id, action, target_type, target_id, detail) VALUES (?, 'project.transfer', 'project', ?, ?)`, u.ID, pid, uid)
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/admin", http.StatusSeeOther)
		return
	}
	httpx.WriteMessage(w, http.StatusOK, "ownership transferred")
}

func setRoleTx(ctx context.Context, db *sql.DB, actorID, targetID int64, role string) error {
	switch role {
	case models.RoleAdmin, models.RoleMaintainer, models.RoleDeveloper, models.RoleReporter, models.RoleMember:
	default:
		return httpx.BadRequest("invalid role")
	}
	if actorID == targetID && role != models.RoleAdmin {
		return httpx.BadRequest("cannot demote yourself")
	}
	if _, err := db.ExecContext(ctx, `UPDATE users SET role=?, updated_at=datetime('now') WHERE id=?`, role, targetID); err != nil {
		return err
	}
	_, _ = db.ExecContext(ctx, `INSERT INTO audit_events(actor_id, action, target_type, target_id, detail) VALUES (?, 'user.role', 'user', ?, ?)`, actorID, targetID, role)
	return nil
}

func IsAdmin(role string) bool { return role == models.RoleAdmin }
func IsMaintainer(role string) bool { return role == models.RoleAdmin || role == models.RoleMaintainer }
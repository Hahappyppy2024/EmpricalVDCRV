package admin

import (
	"net/http"

	"github.com/anomaly/p09/internal/auth"
	"github.com/anomaly/p09/internal/models"
	"github.com/anomaly/p09/internal/templates"
)

func (h *Handler) Page(w http.ResponseWriter, r *http.Request, tpl *templates.Engine) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok || u.Role != models.RoleAdmin {
		http.Error(w, "admin only", http.StatusForbidden)
		return
	}
	settings := map[string]string{}
	rows, err := h.Service.DB.QueryContext(r.Context(), `SELECT key, value FROM settings`)
	if err == nil {
		defer rows.Close()
		for rows.Next() {
			var k, v string
			if err := rows.Scan(&k, &v); err == nil {
				settings[k] = v
			}
		}
	}
	if _, ok := settings["workspace_name"]; !ok {
		settings["workspace_name"] = "Issue Tracker Workspace"
	}
	if _, ok := settings["allow_registration"]; !ok {
		settings["allow_registration"] = "1"
	}
	if _, ok := settings["default_priority"]; !ok {
		settings["default_priority"] = models.PriorityNormal
	}
	users, err := listUsers(r.Context(), h.Service.DB)
	if err != nil {
		users = nil
	}
	projects, err := listProjects(r.Context(), h.Service.DB)
	if err != nil {
		projects = nil
	}
	audits, err := listAudits(r.Context(), h.Service.DB)
	if err != nil {
		audits = nil
	}
	tpl.Render(w, "admin.html", map[string]any{
		"User":        u,
		"Settings":    settings,
		"Users":       users,
		"Projects":    projects,
		"AuditEvents": audits,
		"CSRFToken":   auth.CSRFToken(r.Context()),
	})
}

type userRow struct {
	ID       int64
	Username string
	Email    string
	Role     string
	Status   string
}

func listUsers(ctx interface{ Done() <-chan struct{} }, db sqlDB) ([]userRow, error) {
	rows, err := db.QueryContext(ctxAsCtx(ctx), `SELECT id, username, email, role, status FROM users ORDER BY username`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []userRow
	for rows.Next() {
		var u userRow
		if err := rows.Scan(&u.ID, &u.Username, &u.Email, &u.Role, &u.Status); err != nil {
			return nil, err
		}
		out = append(out, u)
	}
	return out, rows.Err()
}

type projectRow struct {
	ID   int64
	Name string
}

func listProjects(ctx interface{ Done() <-chan struct{} }, db sqlDB) ([]projectRow, error) {
	rows, err := db.QueryContext(ctxAsCtx(ctx), `SELECT id, name FROM projects ORDER BY name`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []projectRow
	for rows.Next() {
		var p projectRow
		if err := rows.Scan(&p.ID, &p.Name); err == nil {
			out = append(out, p)
		}
	}
	return out, rows.Err()
}

type auditRow struct {
	ID         int64
	CreatedAt  string
	Actor      string
	ActorName  string
	Action     string
	TargetType string
	TargetID   *int64
	Detail     string
}

func listAudits(ctx interface{ Done() <-chan struct{} }, db sqlDB) ([]auditRow, error) {
	rows, err := db.QueryContext(ctxAsCtx(ctx), `SELECT a.id, a.created_at, a.actor_id, COALESCE(u.username, ''), a.action, a.target_type, a.target_id, a.detail FROM audit_events a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 50`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []auditRow
	for rows.Next() {
		var a auditRow
		if err := rows.Scan(&a.ID, &a.CreatedAt, &a.Actor, &a.ActorName, &a.Action, &a.TargetType, &a.TargetID, &a.Detail); err != nil {
			return nil, err
		}
		out = append(out, a)
	}
	return out, rows.Err()
}
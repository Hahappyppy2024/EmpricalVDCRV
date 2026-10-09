package issues

import (
	"context"
	"database/sql"
	"fmt"
	"net/http"

	"github.com/go-chi/chi/v5"

	"github.com/anomaly/p09/internal/auth"
	"github.com/anomaly/p09/internal/models"
	"github.com/anomaly/p09/internal/templates"
)

func (h *Handler) NewPage(w http.ResponseWriter, r *http.Request, tpl *templates.Engine) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		http.Redirect(w, r, "/login", http.StatusSeeOther)
		return
	}
	slug := chi.URLParam(r, "slug")
	var projectID int64
	if err := h.Service.DB.QueryRowContext(r.Context(), `SELECT id FROM projects WHERE slug=?`, slug).Scan(&projectID); err != nil {
		http.Error(w, "project not found", http.StatusNotFound)
		return
	}
	type simpleProject struct {
		ID   int64
		Slug string
		Name string
	}
	p := simpleProject{ID: projectID, Slug: slug}
	_ = h.Service.DB.QueryRowContext(r.Context(), `SELECT name FROM projects WHERE id=?`, projectID).Scan(&p.Name)
	labels, _ := listLabels(r.Context(), h.Service.DB, projectID)
	milestones, _ := listMilestones(r.Context(), h.Service.DB, projectID)
	assignees, _ := listProjectUsers(r.Context(), h.Service.DB, projectID)
	tpl.Render(w, "issue_new.html", map[string]any{
		"User":       u,
		"Project":    p,
		"Labels":     labels,
		"Milestones": milestones,
		"Assignees":  assignees,
		"CSRFToken":  auth.CSRFToken(r.Context()),
	})
}

func (h *Handler) ShowPage(w http.ResponseWriter, r *http.Request, tpl *templates.Engine) {
	slug := chi.URLParam(r, "slug")
	numberStr := chi.URLParam(r, "number")
	var number int
	if _, err := fmt.Sscanf(numberStr, "%d", &number); err != nil || number <= 0 {
		http.Error(w, "invalid issue number", http.StatusBadRequest)
		return
	}
	issue, err := h.Service.GetByNumber(r.Context(), slug, number)
	if err != nil {
		http.Error(w, "issue not found", http.StatusNotFound)
		return
	}
	u, _ := auth.UserFromContext(r.Context())
	comments, _ := h.Service.ListComments(r.Context(), issue.ID)
	attachments, _ := h.Service.ListAttachments(r.Context(), issue.ID)
	labels, _ := listLabels(r.Context(), h.Service.DB, issue.ProjectID)
	milestones, _ := listMilestones(r.Context(), h.Service.DB, issue.ProjectID)
	assignees, _ := listProjectUsers(r.Context(), h.Service.DB, issue.ProjectID)
	canManage := false
	if u != nil {
		var role string
		_ = h.Service.DB.QueryRowContext(r.Context(), `SELECT role FROM project_members WHERE project_id=? AND user_id=?`, issue.ProjectID, u.ID).Scan(&role)
		if role == "maintainer" || u.ID == issue.AuthorID {
			canManage = true
		}
		var isAdmin string
		_ = h.Service.DB.QueryRowContext(r.Context(), `SELECT role FROM users WHERE id=?`, u.ID).Scan(&isAdmin)
		if isAdmin == models.RoleAdmin {
			canManage = true
		}
		var ownerID int64
		_ = h.Service.DB.QueryRowContext(r.Context(), `SELECT owner_id FROM projects WHERE id=?`, issue.ProjectID).Scan(&ownerID)
		if ownerID == u.ID {
			canManage = true
		}
	}
	tpl.Render(w, "issue_show.html", map[string]any{
		"User":        u,
		"Issue":       issue,
		"Comments":    comments,
		"Attachments": attachments,
		"Labels":      labels,
		"Milestones":  milestones,
		"Assignees":   assignees,
		"CanManage":   canManage,
		"CSRFToken":   auth.CSRFToken(r.Context()),
	})
}

type labelInfo struct {
	ID    int64
	Name  string
	Color string
}

type milestoneInfo struct {
	ID    int64
	Title string
	State string
}

type userInfo struct {
	ID       int64
	Username string
	Role     string
}

func listLabels(ctx context.Context, db *sql.DB, projectID int64) ([]labelInfo, error) {
	rows, err := db.QueryContext(ctx, `SELECT id, name, color FROM labels WHERE project_id=? ORDER BY name`, projectID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []labelInfo
	for rows.Next() {
		var l labelInfo
		if err := rows.Scan(&l.ID, &l.Name, &l.Color); err != nil {
			return nil, err
		}
		out = append(out, l)
	}
	return out, rows.Err()
}

func listMilestones(ctx context.Context, db *sql.DB, projectID int64) ([]milestoneInfo, error) {
	rows, err := db.QueryContext(ctx, `SELECT id, title, state FROM milestones WHERE project_id=? ORDER BY id`, projectID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []milestoneInfo
	for rows.Next() {
		var m milestoneInfo
		if err := rows.Scan(&m.ID, &m.Title, &m.State); err != nil {
			return nil, err
		}
		out = append(out, m)
	}
	return out, rows.Err()
}

func listProjectUsers(ctx context.Context, db *sql.DB, projectID int64) ([]userInfo, error) {
	rows, err := db.QueryContext(ctx, `SELECT DISTINCT u.id, u.username, u.role FROM users u JOIN project_members pm ON pm.user_id=u.id WHERE pm.project_id=? ORDER BY u.username`, projectID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []userInfo
	for rows.Next() {
		var u userInfo
		if err := rows.Scan(&u.ID, &u.Username, &u.Role); err != nil {
			return nil, err
		}
		out = append(out, u)
	}
	if len(out) == 0 {
		rows2, err := db.QueryContext(ctx, `SELECT id, username, role FROM users ORDER BY username LIMIT 20`)
		if err != nil {
			return out, nil
		}
		defer rows2.Close()
		for rows2.Next() {
			var u userInfo
			if err := rows2.Scan(&u.ID, &u.Username, &u.Role); err == nil {
				out = append(out, u)
			}
		}
	}
	return out, rows.Err()
}
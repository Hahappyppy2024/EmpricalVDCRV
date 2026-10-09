package importexport

import (
	"net/http"

	"github.com/anomaly/p09/internal/auth"
	"github.com/anomaly/p09/internal/httpx"
	"github.com/anomaly/p09/internal/templates"
)

func (h *Handler) Page(w http.ResponseWriter, r *http.Request, tpl *templates.Engine) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		http.Redirect(w, r, "/login", http.StatusSeeOther)
		return
	}
	if u.Role != "admin" && u.Role != "maintainer" && u.Role != "developer" {
		http.Error(w, "forbidden", http.StatusForbidden)
		return
	}
	rows, err := h.Service.DB.QueryContext(r.Context(), `SELECT id, name FROM projects ORDER BY name`)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	defer rows.Close()
	type p struct {
		ID   int64
		Name string
	}
	var projects []p
	for rows.Next() {
		var pp p
		if err := rows.Scan(&pp.ID, &pp.Name); err == nil {
			projects = append(projects, pp)
		}
	}
	jobs, _ := h.listJobs(r.Context())
	tpl.Render(w, "import_export.html", map[string]any{
		"User":      u,
		"Projects":  projects,
		"Jobs":      jobs,
		"CSRFToken": auth.CSRFToken(r.Context()),
	})
}

func (h *Handler) listJobs(ctx interface{ Done() <-chan struct{} }) ([]Job, error) {
	rows, err := h.Service.DB.QueryContext(ctxAsContext(ctx), `SELECT j.id, j.user_id, j.direction, j.project_id, p.name, j.format, j.status, j.detail, j.artifact_path, j.created_at FROM import_export_jobs j JOIN projects p ON p.id=j.project_id ORDER BY j.id DESC LIMIT 50`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []Job
	for rows.Next() {
		var j Job
		if err := rows.Scan(&j.ID, &j.UserID, &j.Direction, &j.ProjectID, &j.ProjectName, &j.Format, &j.Status, &j.Detail, &j.ArtifactPath, &j.CreatedAt); err == nil {
			out = append(out, j)
		}
	}
	return out, rows.Err()
}
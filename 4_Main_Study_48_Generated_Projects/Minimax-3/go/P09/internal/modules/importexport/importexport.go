package importexport

import (
	"context"
	"database/sql"
	"encoding/csv"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"github.com/go-chi/chi/v5"

	"github.com/anomaly/p09/internal/auth"
	"github.com/anomaly/p09/internal/httpx"
	"github.com/anomaly/p09/internal/models"
	"github.com/anomaly/p09/internal/templates"
)

type Service struct {
	DB        *sql.DB
	ExportDir string
}

func NewService(db *sql.DB, exportDir string) *Service {
	return &Service{DB: db, ExportDir: exportDir}
}

type Job struct {
	models.ImportExportJob
	ProjectName string `json:"project_name"`
}

type IssueExport struct {
	ID       int64  `json:"id"`
	Number   int    `json:"number"`
	Title    string `json:"title"`
	Body     string `json:"body"`
	State    string `json:"state"`
	Priority string `json:"priority"`
	Author   string `json:"author_username"`
	Assignee string `json:"assignee_username,omitempty"`
	Labels   []string `json:"labels"`
	Comments []CommentExport `json:"comments"`
}

type CommentExport struct {
	Author string `json:"author_username"`
	Body   string `json:"body"`
	When   string `json:"created_at"`
}

type ProjectExport struct {
	Project     map[string]any `json:"project"`
	Issues      []IssueExport  `json:"issues"`
	ExportedAt  string         `json:"exported_at"`
	ExportedBy  string         `json:"exported_by"`
}

type Handler struct {
	Service *Service
}

func NewHandler(s *Service) *Handler { return &Handler{Service: s} }

func (h *Handler) Routes(r chi.Router, tpl *templates.Engine) {
	r.Get("/api/issue/import_export", h.list)
	r.Post("/api/issue/import_export", h.handle)
	r.Get("/api/issue/import_export/{id}/download", h.download)
	r.Get("/import-export", func(w http.ResponseWriter, req *http.Request) { h.Page(w, req, tpl) })
}

func (h *Handler) list(w http.ResponseWriter, r *http.Request) {
	rows, err := h.Service.DB.QueryContext(r.Context(), `SELECT j.id, j.user_id, j.direction, j.project_id, p.name, j.format, j.status, j.detail, j.artifact_path, j.created_at FROM import_export_jobs j JOIN projects p ON p.id=j.project_id ORDER BY j.id DESC LIMIT 50`)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	defer rows.Close()
	var out []Job
	for rows.Next() {
		var j Job
		if err := rows.Scan(&j.ID, &j.UserID, &j.Direction, &j.ProjectID, &j.ProjectName, &j.Format, &j.Status, &j.Detail, &j.ArtifactPath, &j.CreatedAt); err != nil {
			httpx.WriteError(w, err)
			return
		}
		out = append(out, j)
	}
	httpx.WriteJSON(w, http.StatusOK, out)
}

type handlePayload struct {
	Action    string `json:"action"`
	ProjectID int64  `json:"project_id"`
	Format    string `json:"format"`
}

func (h *Handler) handle(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		httpx.WriteError(w, httpx.Unauthorized("authentication required"))
		return
	}
	action := r.FormValue("action")
	projectIDStr := r.FormValue("project_id")
	if action == "" {
		action = "export"
	}
	pid, err := strconv.ParseInt(projectIDStr, 10, 64)
	if err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid project_id"))
		return
	}
	if !canManageExport(r.Context(), h.Service.DB, u, pid) {
		httpx.WriteError(w, httpx.Forbidden("not allowed"))
		return
	}
	switch action {
	case "export":
		format := r.FormValue("format")
		if format != "json" && format != "csv" {
			format = "json"
		}
		jobID, err := h.runExport(r.Context(), u, pid, format)
		if err != nil {
			httpx.WriteError(w, err)
			return
		}
		if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
			http.Redirect(w, r, fmt.Sprintf("/api/issue/import_export/%d/download", jobID), http.StatusSeeOther)
			return
		}
		httpx.WriteJSON(w, http.StatusCreated, map[string]any{"job_id": jobID, "download_url": fmt.Sprintf("/api/issue/import_export/%d/download", jobID)})
	case "import":
		if err := r.ParseMultipartForm(20 << 20); err != nil {
			httpx.WriteError(w, httpx.BadRequest("invalid multipart"))
			return
		}
		file, _, err := r.FormFile("file")
		if err != nil {
			httpx.WriteError(w, httpx.BadRequest("file required"))
			return
		}
		defer file.Close()
		data, err := io.ReadAll(file)
		if err != nil {
			httpx.WriteError(w, err)
			return
		}
		count, err := h.runImport(r.Context(), u, pid, data)
		if err != nil {
			httpx.WriteError(w, err)
			return
		}
		if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
			http.Redirect(w, r, "/import-export", http.StatusSeeOther)
			return
		}
		httpx.WriteMessage(w, http.StatusOK, fmt.Sprintf("imported %d issues", count))
	default:
		httpx.WriteError(w, httpx.BadRequest("unknown action"))
	}
}

func (h *Handler) runExport(ctx context.Context, u *models.User, projectID int64, format string) (int64, error) {
	row := h.Service.DB.QueryRowContext(ctx, `SELECT name, slug FROM projects WHERE id=?`, projectID)
	var name, slug string
	if err := row.Scan(&name, &slug); err != nil {
		return 0, httpx.NotFound("project not found")
	}
	rows, err := h.Service.DB.QueryContext(ctx, `SELECT i.id, i.number, i.title, i.body, i.state, i.priority, u.username, COALESCE(a.username, '') FROM issues i JOIN users u ON u.id=i.author_id LEFT JOIN users a ON a.id=i.assignee_id WHERE i.project_id=? ORDER BY i.number`, projectID)
	if err != nil {
		return 0, err
	}
	defer rows.Close()
	var issues []IssueExport
	for rows.Next() {
		ie := IssueExport{}
		var assignee sql.NullString
		if err := rows.Scan(&ie.ID, &ie.Number, &ie.Title, &ie.Body, &ie.State, &ie.Priority, &ie.Author, &assignee); err != nil {
			return 0, err
		}
		if assignee.Valid {
			ie.Assignee = assignee.String
		}
		issues = append(issues, ie)
	}
	for i := range issues {
		rows2, err := h.Service.DB.QueryContext(ctx, `SELECT l.name FROM labels l JOIN issue_labels il ON il.label_id=l.id WHERE il.issue_id=?`, issues[i].ID)
		if err == nil {
			for rows2.Next() {
				var name string
				if err := rows2.Scan(&name); err == nil {
					issues[i].Labels = append(issues[i].Labels, name)
				}
			}
			rows2.Close()
		}
		rows3, err := h.Service.DB.QueryContext(ctx, `SELECT u.username, c.body, c.created_at FROM comments c JOIN users u ON u.id=c.author_id WHERE c.issue_id=?`, issues[i].ID)
		if err == nil {
			for rows3.Next() {
				var ce CommentExport
				if err := rows3.Scan(&ce.Author, &ce.Body, &ce.When); err == nil {
					issues[i].Comments = append(issues[i].Comments, ce)
				}
			}
			rows3.Close()
		}
	}
	if format == "csv" {
		return writeCSV(ctx, h.Service, u, projectID, name, issues)
	}
	export := ProjectExport{
		Project: map[string]any{
			"id":   projectID,
			"name": name,
			"slug": slug,
		},
		Issues:     issues,
		ExportedAt: time.Now().UTC().Format(time.RFC3339),
		ExportedBy: u.Username,
	}
	data, err := json.MarshalIndent(export, "", "  ")
	if err != nil {
		return 0, err
	}
	return writeArtifact(ctx, h.Service, u, projectID, "export", "json", data)
}

func writeCSV(ctx context.Context, s *Service, u *models.User, projectID int64, name string, issues []IssueExport) (int64, error) {
	var buf strings.Builder
	w := csv.NewWriter(&buf)
	_ = w.Write([]string{"number", "title", "state", "priority", "author", "assignee", "labels", "body"})
	for _, i := range issues {
		_ = w.Write([]string{strconv.Itoa(i.Number), i.Title, i.State, i.Priority, i.Author, i.Assignee, strings.Join(i.Labels, "|"), i.Body})
	}
	w.Flush()
	return writeArtifact(ctx, s, u, projectID, "export", "csv", []byte(buf.String()))
}

func writeArtifact(ctx context.Context, s *Service, u *models.User, projectID int64, direction, format string, data []byte) (int64, error) {
	res, err := s.DB.ExecContext(ctx, `INSERT INTO import_export_jobs(user_id, direction, project_id, format, status, detail) VALUES (?,?,?,?, 'pending', ?)`, u.ID, direction, projectID, format, fmt.Sprintf("%d bytes", len(data)))
	if err != nil {
		return 0, err
	}
	id, _ := res.LastInsertId()
	filename := filepath.Join(s.ExportDir, fmt.Sprintf("%s-%d-%d.%s", direction, id, projectID, format))
	if err := os.WriteFile(filename, data, 0o644); err != nil {
		_, _ = s.DB.ExecContext(ctx, `UPDATE import_export_jobs SET status='failed', detail=? WHERE id=?`, err.Error(), id)
		return 0, err
	}
	_, _ = s.DB.ExecContext(ctx, `UPDATE import_export_jobs SET status='success', artifact_path=? WHERE id=?`, filename, id)
	_, _ = s.DB.ExecContext(ctx, `INSERT INTO audit_events(actor_id, action, target_type, target_id, detail) VALUES (?, 'export.create', 'project', ?, ?)`, u.ID, projectID, filename)
	return id, nil
}

type importPayload struct {
	Issues []IssueExport `json:"issues"`
}

func (h *Handler) runImport(ctx context.Context, u *models.User, projectID int64, data []byte) (int, error) {
	if len(data) == 0 {
		return 0, httpx.BadRequest("empty payload")
	}
	var payload importPayload
	if err := json.Unmarshal(data, &payload); err != nil {
		return 0, httpx.BadRequest("invalid JSON: " + err.Error())
	}
	res, err := h.Service.DB.ExecContext(ctx, `INSERT INTO import_export_jobs(user_id, direction, project_id, format, status, detail) VALUES (?,?,?, 'json', 'pending', ?)`, u.ID, "import", projectID, fmt.Sprintf("%d issues", len(payload.Issues)))
	if err != nil {
		return 0, err
	}
	jobID, _ := res.LastInsertId()
	count := 0
	for _, ie := range payload.Issues {
		title := strings.TrimSpace(ie.Title)
		if title == "" {
			continue
		}
		var number int
		_ = h.Service.DB.QueryRowContext(ctx, `SELECT COALESCE(MAX(number),0)+1 FROM issues WHERE project_id=?`, projectID).Scan(&number)
		res, err := h.Service.DB.ExecContext(ctx, `INSERT INTO issues(project_id, number, title, body, priority, state, author_id) VALUES (?,?,?,?,?, 'open', ?)`, projectID, number, title, ie.Body, defaultIfEmpty(ie.Priority, "normal"), u.ID)
		if err != nil {
			continue
		}
		newID, _ := res.LastInsertId()
		for _, lbl := range ie.Labels {
			if strings.TrimSpace(lbl) == "" {
				continue
			}
			var labelID int64
			_ = h.Service.DB.QueryRowContext(ctx, `SELECT id FROM labels WHERE project_id=? AND name=?`, projectID, lbl).Scan(&labelID)
			if labelID == 0 {
				res, err := h.Service.DB.ExecContext(ctx, `INSERT INTO labels(project_id, name, color) VALUES (?,?, '#6c757d')`, projectID, lbl)
				if err == nil {
					labelID, _ = res.LastInsertId()
				}
			}
			if labelID != 0 {
				_, _ = h.Service.DB.ExecContext(ctx, `INSERT OR IGNORE INTO issue_labels(issue_id, label_id) VALUES (?,?)`, newID, labelID)
			}
		}
		count++
	}
	_, _ = h.Service.DB.ExecContext(ctx, `UPDATE import_export_jobs SET status='success', detail=? WHERE id=?`, fmt.Sprintf("imported %d issues", count), jobID)
	_, _ = h.Service.DB.ExecContext(ctx, `INSERT INTO audit_events(actor_id, action, target_type, target_id, detail) VALUES (?, 'import.complete', 'project', ?, ?)`, u.ID, projectID, fmt.Sprintf("imported %d", count))
	return count, nil
}

func defaultIfEmpty(s, def string) string {
	if strings.TrimSpace(s) == "" {
		return def
	}
	return s
}

func (h *Handler) download(w http.ResponseWriter, r *http.Request) {
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
	var direction, format, status, path string
	if err := h.Service.DB.QueryRowContext(r.Context(), `SELECT project_id, direction, format, status, COALESCE(artifact_path, '') FROM import_export_jobs WHERE id=?`, id).Scan(&projectID, &direction, &format, &status, &path); err != nil {
		httpx.WriteError(w, httpx.NotFound("job not found"))
		return
	}
	if !canManageExport(r.Context(), h.Service.DB, u, projectID) {
		httpx.WriteError(w, httpx.Forbidden("not allowed"))
		return
	}
	if path == "" {
		httpx.WriteError(w, httpx.NotFound("no artifact"))
		return
	}
	w.Header().Set("Content-Type", "application/octet-stream")
	w.Header().Set("Content-Disposition", fmt.Sprintf("attachment; filename=\"job-%d.%s\"", id, format))
	http.ServeFile(w, r, path)
}

func canManageExport(ctx context.Context, db *sql.DB, u *models.User, projectID int64) bool {
	if u == nil {
		return false
	}
	if u.Role == models.RoleAdmin || u.Role == models.RoleMaintainer {
		return true
	}
	var ownerID int64
	_ = db.QueryRowContext(ctx, `SELECT owner_id FROM projects WHERE id=?`, projectID).Scan(&ownerID)
	return ownerID == u.ID
}

var ErrInvalid = errors.New("invalid")
package private

import (
	"context"
	"database/sql"
	"net/http"
	"strconv"

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

type Access struct {
	ProjectID int64  `json:"project_id"`
	UserID    int64  `json:"user_id"`
	Username  string `json:"username"`
	Role      string `json:"role"`
	GrantedBy int64  `json:"granted_by"`
	CreatedAt string `json:"created_at"`
}

type Handler struct {
	Service *Service
}

func NewHandler(s *Service) *Handler { return &Handler{Service: s} }

func (h *Handler) Routes(r chi.Router, tpl *templates.Engine) {
	r.Get("/api/issue/private_projects", h.list)
	r.Post("/api/issue/private_projects/grant/{projectID}", h.grant)
	r.Post("/api/issue/private_projects/revoke/{projectID}/{userID}", h.revoke)
}

func (h *Handler) list(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		httpx.WriteError(w, httpx.Unauthorized("authentication required"))
		return
	}
	rows, err := h.Service.DB.QueryContext(r.Context(), `SELECT pa.project_id, pa.user_id, u.username, COALESCE(pm.role, 'viewer'), pa.granted_by, pa.created_at FROM private_access pa JOIN users u ON u.id=pa.user_id LEFT JOIN project_members pm ON pm.user_id=pa.user_id AND pm.project_id=pa.project_id WHERE pa.project_id IN (SELECT id FROM projects WHERE visibility='private' AND (owner_id=? OR ?='admin')) ORDER BY pa.project_id, u.username`, u.ID, u.Role)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	defer rows.Close()
	var out []Access
	for rows.Next() {
		a := Access{}
		if err := rows.Scan(&a.ProjectID, &a.UserID, &a.Username, &a.Role, &a.GrantedBy, &a.CreatedAt); err != nil {
			httpx.WriteError(w, err)
			return
		}
		out = append(out, a)
	}
	httpx.WriteJSON(w, http.StatusOK, out)
}

func (h *Handler) grant(w http.ResponseWriter, r *http.Request) {
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
	var visibility string
	var ownerID int64
	if err := h.Service.DB.QueryRowContext(r.Context(), `SELECT visibility, owner_id FROM projects WHERE id=?`, projectID).Scan(&visibility, &ownerID); err != nil {
		httpx.WriteError(w, httpx.NotFound("project not found"))
		return
	}
	if ownerID != u.ID && u.Role != models.RoleAdmin && u.Role != models.RoleMaintainer {
		httpx.WriteError(w, httpx.Forbidden("only owner or admin can grant access"))
		return
	}
	if err := r.ParseForm(); err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid form"))
		return
	}
	uid, err := strconv.ParseInt(r.FormValue("user_id"), 10, 64)
	if err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid user id"))
		return
	}
	role := r.FormValue("role")
	if role != "member" && role != "maintainer" {
		role = "member"
	}
	if _, err := h.Service.DB.ExecContext(r.Context(), `INSERT OR IGNORE INTO private_access(project_id, user_id, granted_by) VALUES (?,?,?)`, projectID, uid, u.ID); err != nil {
		httpx.WriteError(w, err)
		return
	}
	if _, err := h.Service.DB.ExecContext(r.Context(), `INSERT OR IGNORE INTO project_members(project_id, user_id, role) VALUES (?,?,?)`, projectID, uid, role); err != nil {
		httpx.WriteError(w, err)
		return
	}
	_, _ = h.Service.DB.ExecContext(r.Context(), `INSERT INTO audit_events(actor_id, action, target_type, target_id, detail) VALUES (?, 'private.grant', 'project', ?, ?)`, u.ID, projectID, uid)
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/projects/"+lookupSlug(r.Context(), h.Service.DB, projectID), http.StatusSeeOther)
		return
	}
	httpx.WriteMessage(w, http.StatusOK, "access granted")
}

func (h *Handler) revoke(w http.ResponseWriter, r *http.Request) {
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
	uid, err := strconv.ParseInt(chi.URLParam(r, "userID"), 10, 64)
	if err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid user id"))
		return
	}
	var ownerID int64
	_ = h.Service.DB.QueryRowContext(r.Context(), `SELECT owner_id FROM projects WHERE id=?`, projectID).Scan(&ownerID)
	if ownerID != u.ID && u.Role != models.RoleAdmin {
		httpx.WriteError(w, httpx.Forbidden("only owner or admin can revoke"))
		return
	}
	_, _ = h.Service.DB.ExecContext(r.Context(), `DELETE FROM private_access WHERE project_id=? AND user_id=?`, projectID, uid)
	_, _ = h.Service.DB.ExecContext(r.Context(), `DELETE FROM project_members WHERE project_id=? AND user_id=?`, projectID, uid)
	_, _ = h.Service.DB.ExecContext(r.Context(), `INSERT INTO audit_events(actor_id, action, target_type, target_id, detail) VALUES (?, 'private.revoke', 'project', ?, ?)`, u.ID, projectID, uid)
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/projects/"+lookupSlug(r.Context(), h.Service.DB, projectID), http.StatusSeeOther)
		return
	}
	httpx.WriteMessage(w, http.StatusOK, "access revoked")
}

func lookupSlug(ctx context.Context, db *sql.DB, id int64) string {
	var slug string
	_ = db.QueryRowContext(ctx, `SELECT slug FROM projects WHERE id=?`, id).Scan(&slug)
	return slug
}
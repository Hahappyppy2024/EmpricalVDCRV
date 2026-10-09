package projects

import (
	"context"
	"net/http"
	"strconv"

	"github.com/go-chi/chi/v5"

	"github.com/anomaly/p09/internal/auth"
	"github.com/anomaly/p09/internal/httpx"
	"github.com/anomaly/p09/internal/realtime"
	"github.com/anomaly/p09/internal/templates"
)

type Handler struct {
	Service *Service
	Hub     *realtime.Hub
}

func NewHandler(s *Service, hub *realtime.Hub) *Handler {
	return &Handler{Service: s, Hub: hub}
}

func (h *Handler) Routes(r chi.Router, tpl *templates.Engine) {
	r.Get("/api/issue/project_management", h.list)
	r.Post("/api/issue/project_management", h.create)
	r.Post("/api/issue/project_management/{id}", h.updateViaPost)
	r.Route("/api/issue/project_management/{id}", func(sub chi.Router) {
		sub.Patch("/", h.update)
		sub.Post("/labels", h.addLabel)
		sub.Post("/milestones", h.addMilestone)
		sub.Post("/members", h.addMember)
	})
	r.Get("/projects", func(w http.ResponseWriter, req *http.Request) { h.ListPage(w, req, tpl) })
	r.Get("/projects/new", func(w http.ResponseWriter, req *http.Request) { h.NewPage(w, req, tpl) })
	r.Get("/projects/{slug}", func(w http.ResponseWriter, req *http.Request) { h.ShowPage(w, req, tpl) })
	r.Get("/projects/{slug}/search", func(w http.ResponseWriter, req *http.Request) { h.searchInProject(w, req) })
}

type projectPayload struct {
	Slug        string `json:"slug"`
	Name        string `json:"name"`
	Description string `json:"description"`
	Visibility  string `json:"visibility"`
	Archived    *bool  `json:"archived"`
	Method      string `json:"_method"`
}

func (h *Handler) list(w http.ResponseWriter, r *http.Request) {
	u, _ := auth.UserFromContext(r.Context())
	var uid int64
	if u != nil {
		uid = u.ID
	}
	q := r.URL.Query().Get("q")
	page, _ := strconv.Atoi(r.URL.Query().Get("page"))
	if page < 1 {
		page = 1
	}
	const pageSize = 20
	projects, total, err := h.Service.List(r.Context(), uid, q, pageSize, (page-1)*pageSize)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	httpx.WriteJSON(w, http.StatusOK, map[string]any{"projects": projects, "total": total, "page": page})
}

func (h *Handler) create(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		httpx.WriteError(w, httpx.Unauthorized("authentication required"))
		return
	}
	var in projectPayload
	if err := r.ParseForm(); err == nil {
		in.Slug = r.FormValue("slug")
		in.Name = r.FormValue("name")
		in.Description = r.FormValue("description")
		in.Visibility = r.FormValue("visibility")
	} else {
		if err := httpx.ReadJSON(r, &in); err != nil {
			httpx.WriteError(w, httpx.BadRequest("invalid input"))
			return
		}
	}
	project, err := h.Service.Create(r.Context(), u.ID, in.Slug, in.Name, in.Description, in.Visibility)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/projects/"+project.Slug, http.StatusSeeOther)
		return
	}
	httpx.WriteJSON(w, http.StatusCreated, project)
}

func (h *Handler) updateViaPost(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid form"))
		return
	}
	if r.FormValue("_method") == "PATCH" {
		h.update(w, r)
		return
	}
	httpx.WriteError(w, httpx.BadRequest("only PATCH supported via POST"))
}

func (h *Handler) update(w http.ResponseWriter, r *http.Request) {
	id, err := httpx.ParseID(chi.URLParam(r, "id"))
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		httpx.WriteError(w, httpx.Unauthorized("authentication required"))
		return
	}
	if !h.Service.CanManage(r.Context(), u.ID, id) {
		httpx.WriteError(w, httpx.Forbidden("not allowed"))
		return
	}
	var in projectPayload
	if err := r.ParseForm(); err == nil {
		in.Name = r.FormValue("name")
		in.Description = r.FormValue("description")
		in.Visibility = r.FormValue("visibility")
		arch := r.FormValue("archived") == "1"
		in.Archived = &arch
	} else {
		if err := httpx.ReadJSON(r, &in); err != nil {
			httpx.WriteError(w, httpx.BadRequest("invalid input"))
			return
		}
	}
	archived := false
	if in.Archived != nil {
		archived = *in.Archived
	}
	if err := h.Service.Update(r.Context(), id, in.Name, in.Description, in.Visibility, archived); err != nil {
		httpx.WriteError(w, err)
		return
	}
	_, _ = h.Service.DB.ExecContext(r.Context(), `INSERT INTO audit_events(actor_id, action, target_type, target_id, detail) VALUES (?, 'project.update', 'project', ?, ?)`, u.ID, id, in.Name)
	project, err := h.Service.Get(r.Context(), id)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/projects/"+project.Slug, http.StatusSeeOther)
		return
	}
	httpx.WriteJSON(w, http.StatusOK, project)
}

func (h *Handler) addLabel(w http.ResponseWriter, r *http.Request) {
	id, err := httpx.ParseID(chi.URLParam(r, "id"))
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	u, _ := auth.UserFromContext(r.Context())
	if u == nil || !h.Service.CanManage(r.Context(), u.ID, id) {
		httpx.WriteError(w, httpx.Forbidden("not allowed"))
		return
	}
	if err := r.ParseForm(); err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid form"))
		return
	}
	if err := h.Service.AddLabel(r.Context(), id, r.FormValue("name"), r.FormValue("color")); err != nil {
		httpx.WriteError(w, err)
		return
	}
	project, _ := h.Service.Get(r.Context(), id)
	if project != nil {
		http.Redirect(w, r, "/projects/"+project.Slug, http.StatusSeeOther)
		return
	}
	httpx.WriteMessage(w, http.StatusCreated, "label added")
}

func (h *Handler) addMilestone(w http.ResponseWriter, r *http.Request) {
	id, err := httpx.ParseID(chi.URLParam(r, "id"))
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	u, _ := auth.UserFromContext(r.Context())
	if u == nil || !h.Service.CanManage(r.Context(), u.ID, id) {
		httpx.WriteError(w, httpx.Forbidden("not allowed"))
		return
	}
	if err := r.ParseForm(); err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid form"))
		return
	}
	if err := h.Service.AddMilestone(r.Context(), id, r.FormValue("title"), r.FormValue("description"), r.FormValue("due_date")); err != nil {
		httpx.WriteError(w, err)
		return
	}
	project, _ := h.Service.Get(r.Context(), id)
	if project != nil {
		http.Redirect(w, r, "/projects/"+project.Slug, http.StatusSeeOther)
		return
	}
	httpx.WriteMessage(w, http.StatusCreated, "milestone added")
}

func (h *Handler) addMember(w http.ResponseWriter, r *http.Request) {
	id, err := httpx.ParseID(chi.URLParam(r, "id"))
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	u, _ := auth.UserFromContext(r.Context())
	if u == nil || !h.Service.CanManage(r.Context(), u.ID, id) {
		httpx.WriteError(w, httpx.Forbidden("not allowed"))
		return
	}
	if err := r.ParseForm(); err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid form"))
		return
	}
	uid, err := httpx.ParseID(r.FormValue("user_id"))
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	if err := h.Service.AddMember(r.Context(), id, uid, r.FormValue("role")); err != nil {
		httpx.WriteError(w, err)
		return
	}
	project, _ := h.Service.Get(r.Context(), id)
	if project != nil {
		http.Redirect(w, r, "/projects/"+project.Slug, http.StatusSeeOther)
		return
	}
	httpx.WriteMessage(w, http.StatusCreated, "member added")
}

func (h *Handler) ListPage(w http.ResponseWriter, r *http.Request, tpl *templates.Engine) {
	u, _ := auth.UserFromContext(r.Context())
	var uid int64
	if u != nil {
		uid = u.ID
	}
	q := r.URL.Query().Get("q")
	page, _ := strconv.Atoi(r.URL.Query().Get("page"))
	if page < 1 {
		page = 1
	}
	const pageSize = 20
	projects, total, err := h.Service.List(r.Context(), uid, q, pageSize, (page-1)*pageSize)
	if err != nil {
		projects = nil
	}
	totalPages := (total + pageSize - 1) / pageSize
	tpl.Render(w, "projects.html", map[string]any{
		"User":        u,
		"Projects":    projects,
		"Query":       q,
		"Page":        page,
		"TotalPages":  totalPages,
		"TotalItems":  total,
		"CSRFToken":   auth.CSRFToken(r.Context()),
	})
}

func (h *Handler) NewPage(w http.ResponseWriter, r *http.Request, tpl *templates.Engine) {
	u, _ := auth.UserFromContext(r.Context())
	if u == nil {
		http.Redirect(w, r, "/login?next=/projects/new", http.StatusSeeOther)
		return
	}
	tpl.Render(w, "project_new.html", map[string]any{
		"User":      u,
		"CSRFToken": auth.CSRFToken(r.Context()),
	})
}

func (h *Handler) ShowPage(w http.ResponseWriter, r *http.Request, tpl *templates.Engine) {
	slug := chi.URLParam(r, "slug")
	project, err := h.Service.GetBySlug(r.Context(), slug)
	if err != nil {
		http.Error(w, "project not found", http.StatusNotFound)
		return
	}
	u, _ := auth.UserFromContext(r.Context())
	var uid int64
	if u != nil {
		uid = u.ID
	}
	allowed, err := h.Service.CanView(r.Context(), uid, project.ID)
	if err != nil || !allowed {
		http.Error(w, "project not visible", http.StatusForbidden)
		return
	}
	canManage := u != nil && h.Service.CanManage(r.Context(), u.ID, project.ID)
	labels, _ := h.Service.ListLabels(r.Context(), project.ID)
	milestones, _ := h.Service.ListMilestones(r.Context(), project.ID)
	members, _ := h.Service.ListMembers(r.Context(), project.ID)
	issues, _ := listIssuesForProject(r.Context(), h.Service.DB, project.ID, uid, canManage, 25)
	users, _ := listAllUsers(r.Context(), h.Service.DB)
	webhooks, _ := listWebhooksForProject(r.Context(), h.Service.DB, project.ID)
	tpl.Render(w, "project_show.html", map[string]any{
		"User":             u,
		"Project":          project,
		"Labels":           labels,
		"Milestones":       milestones,
		"Members":          members,
		"Issues":           issues,
		"Webhooks":         webhooks,
		"AllUsers":         users,
		"CanManage":        canManage,
		"CanCreateIssue":   u != nil,
		"CSRFToken":        auth.CSRFToken(r.Context()),
	})
}

func (h *Handler) searchInProject(w http.ResponseWriter, r *http.Request) {
	http.Redirect(w, r, "/search?project="+chi.URLParam(r, "slug"), http.StatusSeeOther)
}

func listAllUsers(ctx context.Context, db *sqlQuerier) ([]*userBrief, error) {
	rows, err := db.QueryContext(ctx, `SELECT id, username, role FROM users ORDER BY username`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []*userBrief
	for rows.Next() {
		u := &userBrief{}
		if err := rows.Scan(&u.ID, &u.Username, &u.Role); err != nil {
			return nil, err
		}
		out = append(out, u)
	}
	return out, rows.Err()
}
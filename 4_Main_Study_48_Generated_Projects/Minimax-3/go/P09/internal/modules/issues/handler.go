package issues

import (
	"database/sql"
	"net/http"

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
	r.Get("/api/issue/issue_creation", h.list)
	r.Post("/api/issue/issue_creation/{projectID}", h.create)
	r.Get("/projects/{slug}/issues/new", func(w http.ResponseWriter, req *http.Request) { h.NewPage(w, req, tpl) })
	r.Get("/projects/{slug}/issues/{number}", func(w http.ResponseWriter, req *http.Request) { h.ShowPage(w, req, tpl) })
}

type createPayload struct {
	Title       string  `json:"title"`
	Body        string  `json:"body"`
	Priority    string  `json:"priority"`
	AssigneeID  *int64  `json:"assignee_id"`
	MilestoneID *int64  `json:"milestone_id"`
	Labels      []int64 `json:"labels"`
}

func (h *Handler) list(w http.ResponseWriter, r *http.Request) {
	rows, err := h.Service.DB.QueryContext(r.Context(), `SELECT id, project_id, number, title, '', '', state, author_id, assignee_id, milestone_id, created_at, updated_at, COALESCE(closed_at,'') FROM issues ORDER BY id DESC LIMIT 50`)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	defer rows.Close()
	type brief struct {
		ID     int64  `json:"id"`
		Number int    `json:"number"`
		Title  string `json:"title"`
		State  string `json:"state"`
	}
	var out []brief
	for rows.Next() {
		var b brief
		var assignee, milestone sql.NullInt64
		var created, updated string
		if err := rows.Scan(&b.ID, new(int64), &b.Number, &b.Title, new(string), new(string), &b.State, new(int64), &assignee, &milestone, &created, &updated, new(string)); err != nil {
			httpx.WriteError(w, err)
			return
		}
		out = append(out, b)
	}
	httpx.WriteJSON(w, http.StatusOK, out)
}

func (h *Handler) create(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		httpx.WriteError(w, httpx.Unauthorized("authentication required"))
		return
	}
	projectID, err := httpx.ParseID(chi.URLParam(r, "projectID"))
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	var in createPayload
	if err := r.ParseMultipartForm(8 << 20); err == nil {
		in.Title = r.FormValue("title")
		in.Body = r.FormValue("body")
		in.Priority = r.FormValue("priority")
		if v := r.FormValue("assignee_id"); v != "" {
			id, err := httpx.ParseID(v)
			if err == nil {
				in.AssigneeID = &id
			}
		}
		if v := r.FormValue("milestone_id"); v != "" {
			id, err := httpx.ParseID(v)
			if err == nil {
				in.MilestoneID = &id
			}
		}
		for _, raw := range r.Form["labels"] {
			id, err := httpx.ParseID(raw)
			if err == nil {
				in.Labels = append(in.Labels, id)
			}
		}
	} else if err := r.ParseForm(); err == nil {
		in.Title = r.FormValue("title")
		in.Body = r.FormValue("body")
		in.Priority = r.FormValue("priority")
		if v := r.FormValue("assignee_id"); v != "" {
			id, err := httpx.ParseID(v)
			if err == nil {
				in.AssigneeID = &id
			}
		}
		if v := r.FormValue("milestone_id"); v != "" {
			id, err := httpx.ParseID(v)
			if err == nil {
				in.MilestoneID = &id
			}
		}
		for _, raw := range r.Form["labels"] {
			id, err := httpx.ParseID(raw)
			if err == nil {
				in.Labels = append(in.Labels, id)
			}
		}
	} else if err := httpx.ReadJSON(r, &in); err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid input"))
		return
	}
	issue, err := h.Service.Create(r.Context(), projectID, u.ID, in.Title, in.Body, in.Priority, in.AssigneeID, in.MilestoneID, in.Labels)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	if h.Hub != nil {
		h.Hub.Publish(realtime.Event{Event: "issue.opened", Message: "issue #" + itoa(issue.Number) + " opened: " + issue.Title, IssueID: issue.ID, ActorID: u.ID})
	}
	_, _ = h.Service.DB.ExecContext(r.Context(), `INSERT INTO audit_events(actor_id, action, target_type, target_id, detail) VALUES (?, 'issue.create', 'issue', ?, ?)`, u.ID, issue.ID, issue.Title)
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/projects/"+issue.ProjectSlug+"/issues/"+itoa(issue.Number), http.StatusSeeOther)
		return
	}
	httpx.WriteJSON(w, http.StatusCreated, issue)
}

func itoa(i int) string {
	if i == 0 {
		return "0"
	}
	neg := i < 0
	if neg {
		i = -i
	}
	buf := make([]byte, 0, 12)
	for i > 0 {
		buf = append([]byte{byte('0' + i%10)}, buf...)
		i /= 10
	}
	if neg {
		buf = append([]byte{'-'}, buf...)
	}
	return string(buf)
}
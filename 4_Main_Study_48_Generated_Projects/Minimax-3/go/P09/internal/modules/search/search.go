package search

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"net/http"
	"strconv"
	"strings"

	"github.com/go-chi/chi/v5"
	"github.com/coder/websocket"

	"github.com/anomaly/p09/internal/auth"
	"github.com/anomaly/p09/internal/httpx"
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

type Result struct {
	ID               int64
	Number           int
	Title            string
	State            string
	Priority         string
	ProjectSlug      string
	ProjectName      string
	AssigneeUsername string
	Labels           []LabelBrief
}

type LabelBrief struct {
	ID    int64
	Name  string
	Color string
}

type Filters struct {
	Query       string
	State       string
	Priority    string
	LabelName   string
	Assignee    string
	ProjectSlug string
}

func (s *Service) Search(ctx context.Context, userID int64, f Filters, limit, offset int) ([]Result, int, error) {
	conditions := []string{"1=1"}
	args := []any{}
	if userID > 0 {
		conditions = append(conditions, fmt.Sprintf("(p.visibility='public' OR p.owner_id=? OR p.id IN (SELECT project_id FROM project_members WHERE user_id=?) OR p.id IN (SELECT project_id FROM private_access WHERE user_id=?))"))
		args = append(args, userID, userID, userID)
	} else {
		conditions = append(conditions, "p.visibility='public'")
	}
	if strings.TrimSpace(f.Query) != "" {
		conditions = append(conditions, "(i.title LIKE ? OR i.body LIKE ?)")
		like := "%" + f.Query + "%"
		args = append(args, like, like)
	}
	if f.State != "" {
		conditions = append(conditions, "i.state=?")
		args = append(args, f.State)
	}
	if f.Priority != "" {
		conditions = append(conditions, "i.priority=?")
		args = append(args, f.Priority)
	}
	if f.LabelName != "" {
		conditions = append(conditions, "i.id IN (SELECT il.issue_id FROM issue_labels il JOIN labels l ON l.id=il.label_id WHERE l.name=?)")
		args = append(args, f.LabelName)
	}
	if f.ProjectSlug != "" {
		conditions = append(conditions, "p.slug=?")
		args = append(args, f.ProjectSlug)
	}
	switch f.Assignee {
	case "me":
		if userID > 0 {
			conditions = append(conditions, "i.assignee_id=?")
			args = append(args, userID)
		}
	case "none":
		conditions = append(conditions, "i.assignee_id IS NULL")
	default:
		if f.Assignee != "" {
			conditions = append(conditions, "i.assignee_id IN (SELECT id FROM users WHERE username=?)")
			args = append(args, f.Assignee)
		}
	}
	q := `SELECT i.id, i.number, i.title, i.state, i.priority, p.slug, p.name, COALESCE((SELECT username FROM users WHERE id=i.assignee_id), '') FROM issues i JOIN projects p ON p.id=i.project_id WHERE ` + strings.Join(conditions, " AND ") + ` ORDER BY i.id DESC LIMIT ? OFFSET ?`
	args = append(args, limit, offset)
	rows, err := s.DB.QueryContext(ctx, q, args...)
	if err != nil {
		return nil, 0, err
	}
	defer rows.Close()
	var out []Result
	for rows.Next() {
		r := Result{}
		if err := rows.Scan(&r.ID, &r.Number, &r.Title, &r.State, &r.Priority, &r.ProjectSlug, &r.ProjectName, &r.AssigneeUsername); err != nil {
			return nil, 0, err
		}
		out = append(out, r)
	}
	for i := range out {
		labels, _ := labelsForIssue(ctx, s.DB, out[i].ID)
		out[i].Labels = labels
	}
	var total int
	countQ := `SELECT COUNT(*) FROM issues i JOIN projects p ON p.id=i.project_id WHERE ` + strings.Join(conditions, " AND ")
	if err := s.DB.QueryRowContext(ctx, countQ, args[:len(args)-2]...).Scan(&total); err != nil {
		return nil, 0, err
	}
	return out, total, rows.Err()
}

func labelsForIssue(ctx context.Context, db *sql.DB, issueID int64) ([]LabelBrief, error) {
	rows, err := db.QueryContext(ctx, `SELECT l.id, l.name, l.color FROM labels l JOIN issue_labels il ON il.label_id=l.id WHERE il.issue_id=? ORDER BY l.name`, issueID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []LabelBrief
	for rows.Next() {
		var l LabelBrief
		if err := rows.Scan(&l.ID, &l.Name, &l.Color); err != nil {
			return nil, err
		}
		out = append(out, l)
	}
	return out, rows.Err()
}

func (s *Service) LiveStream(w http.ResponseWriter, r *http.Request) {
	issueIDRaw := r.URL.Query().Get("issue")
	var issueID int64
	if issueIDRaw != "" {
		if id, err := strconv.ParseInt(issueIDRaw, 10, 64); err == nil {
			issueID = id
		}
	}
	conn, err := websocket.Accept(w, r, &websocket.AcceptOptions{
		InsecureSkipVerify: true,
		OriginPatterns:     []string{"*"},
	})
	if err != nil {
		return
	}
	s.Hub.Handle(issueID, conn)
}

type Handler struct {
	Service *Service
}

func NewHandler(s *Service) *Handler {
	return &Handler{Service: s}
}

func (h *Handler) Routes(r chi.Router, tpl *templates.Engine) {
	r.Get("/api/issue/issue_search", h.searchAPI)
	r.Get("/api/issue/issue_search/ws", h.Service.LiveStream)
}

func (h *Handler) searchAPI(w http.ResponseWriter, r *http.Request) {
	u, _ := auth.UserFromContext(r.Context())
	var uid int64
	if u != nil {
		uid = u.ID
	}
	f := Filters{
		Query:       r.URL.Query().Get("q"),
		State:       r.URL.Query().Get("state"),
		Priority:    r.URL.Query().Get("priority"),
		LabelName:   r.URL.Query().Get("label"),
		Assignee:    r.URL.Query().Get("assignee"),
		ProjectSlug: r.URL.Query().Get("project"),
	}
	page, _ := strconv.Atoi(r.URL.Query().Get("page"))
	if page < 1 {
		page = 1
	}
	const pageSize = 20
	results, total, err := h.Service.Search(r.Context(), uid, f, pageSize, (page-1)*pageSize)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	httpx.WriteJSON(w, http.StatusOK, map[string]any{"results": results, "total": total, "page": page})
}

type PageFilterView struct {
	Users    []filterUser
	Projects []filterProject
	Labels   []filterLabel
}

type filterUser struct {
	ID       int64
	Username string
	Role     string
}

type filterProject struct {
	Slug string
	Name string
}

type filterLabel struct {
	Name string
}

func (h *Handler) SearchPage(w http.ResponseWriter, r *http.Request, tpl *templates.Engine) {
	u, _ := auth.UserFromContext(r.Context())
	var uid int64
	if u != nil {
		uid = u.ID
	}
	f := Filters{
		Query:       r.URL.Query().Get("q"),
		State:       r.URL.Query().Get("state"),
		Priority:    r.URL.Query().Get("priority"),
		LabelName:   r.URL.Query().Get("label"),
		Assignee:    r.URL.Query().Get("assignee"),
		ProjectSlug: r.URL.Query().Get("project"),
	}
	page, _ := strconv.Atoi(r.URL.Query().Get("page"))
	if page < 1 {
		page = 1
	}
	const pageSize = 20
	results, total, err := h.Service.Search(r.Context(), uid, f, pageSize, (page-1)*pageSize)
	if err != nil {
		results = nil
	}
	view, _ := h.loadFilterView(r.Context(), uid)
	totalPages := (total + pageSize - 1) / pageSize
	tpl.Render(w, "search.html", map[string]any{
		"User":            u,
		"Query":           f.Query,
		"State":           f.State,
		"Priority":        f.Priority,
		"LabelFilter":     f.LabelName,
		"AssigneeFilter":  f.Assignee,
		"ProjectFilter":   f.ProjectSlug,
		"Results":         results,
		"Total":           total,
		"Page":            page,
		"TotalPages":      totalPages,
		"TotalItems":      total,
		"Users":           view.Users,
		"Projects":        view.Projects,
		"Labels":          view.Labels,
		"CSRFToken":       auth.CSRFToken(r.Context()),
	})
}

func (h *Handler) loadFilterView(ctx context.Context, uid int64) (PageFilterView, error) {
	v := PageFilterView{}
	rows, err := h.Service.DB.QueryContext(ctx, `SELECT id, username, role FROM users ORDER BY username LIMIT 50`)
	if err == nil {
		defer rows.Close()
		for rows.Next() {
			var u filterUser
			if err := rows.Scan(&u.ID, &u.Username, &u.Role); err == nil {
				v.Users = append(v.Users, u)
			}
		}
	}
	projQ := `SELECT slug, name FROM projects p WHERE visibility='public'`
	args := []any{}
	if uid > 0 {
		projQ += ` OR p.owner_id=? OR p.id IN (SELECT project_id FROM project_members WHERE user_id=?) OR p.id IN (SELECT project_id FROM private_access WHERE user_id=?) ORDER BY name`
		args = append(args, uid, uid, uid)
	} else {
		projQ += ` ORDER BY name`
	}
	rows2, err := h.Service.DB.QueryContext(ctx, projQ, args...)
	if err == nil {
		defer rows2.Close()
		for rows2.Next() {
			var p filterProject
			if err := rows2.Scan(&p.Slug, &p.Name); err == nil {
				v.Projects = append(v.Projects, p)
			}
		}
	}
	rows3, err := h.Service.DB.QueryContext(ctx, `SELECT DISTINCT name FROM labels ORDER BY name`)
	if err == nil {
		defer rows3.Close()
		for rows3.Next() {
			var l filterLabel
			if err := rows3.Scan(&l.Name); err == nil {
				v.Labels = append(v.Labels, l)
			}
		}
	}
	return v, nil
}

var ErrNotFound = errors.New("not found")
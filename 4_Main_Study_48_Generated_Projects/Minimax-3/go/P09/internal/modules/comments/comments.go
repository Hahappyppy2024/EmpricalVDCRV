package comments

import (
	"context"
	"database/sql"
	"net/http"
	"strconv"
	"strings"

	"github.com/go-chi/chi/v5"

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

func (s *Service) Create(ctx context.Context, issueID, authorID int64, body string) (int64, error) {
	body = strings.TrimSpace(body)
	if body == "" {
		return 0, httpx.BadRequest("body required")
	}
	res, err := s.DB.ExecContext(ctx, `INSERT INTO comments(issue_id, author_id, body) VALUES (?,?,?)`, issueID, authorID, body)
	if err != nil {
		return 0, err
	}
	id, _ := res.LastInsertId()
	if s.Hub != nil {
		s.Hub.Publish(realtime.Event{Event: "issue.commented", Message: "new comment on issue", IssueID: issueID, ActorID: authorID})
	}
	return id, nil
}

func (s *Service) Delete(ctx context.Context, commentID, userID int64) error {
	res, err := s.DB.ExecContext(ctx, `DELETE FROM comments WHERE id=? AND (author_id=? OR ? IN (SELECT id FROM users WHERE role IN ('admin','maintainer')))`, commentID, userID, userID)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return httpx.Forbidden("not allowed")
	}
	if s.Hub != nil {
		var issueID int64
		_ = s.DB.QueryRowContext(ctx, `SELECT issue_id FROM comments WHERE id=?`, commentID).Scan(&issueID)
		s.Hub.Publish(realtime.Event{Event: "comment.deleted", Message: "comment removed", IssueID: issueID, ActorID: userID})
	}
	return nil
}

type Handler struct {
	Service *Service
}

func NewHandler(s *Service) *Handler { return &Handler{Service: s} }

func (h *Handler) Routes(r chi.Router, tpl *templates.Engine) {
	r.Get("/api/issue/comments", h.list)
	r.Post("/api/issue/comments/{issueID}", h.create)
	r.Post("/api/issue/comments/{id}/delete", h.delete)
}

type createPayload struct {
	Body string `json:"body"`
}

func (h *Handler) list(w http.ResponseWriter, r *http.Request) {
	issueID, err := httpx.ParseID(r.URL.Query().Get("issue_id"))
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	rows, err := h.Service.DB.QueryContext(r.Context(), `SELECT c.id, c.issue_id, c.author_id, c.body, c.created_at, c.updated_at, u.username FROM comments c JOIN users u ON u.id=c.author_id WHERE c.issue_id=? ORDER BY c.id`, issueID)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	defer rows.Close()
	var out []map[string]any
	for rows.Next() {
		var id, issueID, authorID int64
		var body, created, updated, username string
		if err := rows.Scan(&id, &issueID, &authorID, &body, &created, &updated, &username); err != nil {
			httpx.WriteError(w, err)
			return
		}
		out = append(out, map[string]any{
			"id": id, "issue_id": issueID, "author_id": authorID, "author_username": username,
			"body": body, "created_at": created, "updated_at": updated,
		})
	}
	httpx.WriteJSON(w, http.StatusOK, out)
}

func (h *Handler) create(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		httpx.WriteError(w, httpx.Unauthorized("authentication required"))
		return
	}
	issueID, err := httpx.ParseID(chi.URLParam(r, "issueID"))
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	var in createPayload
	if err := r.ParseForm(); err == nil {
		in.Body = r.FormValue("body")
	} else if err := httpx.ReadJSON(r, &in); err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid input"))
		return
	}
	if _, err := h.Service.Create(r.Context(), issueID, u.ID, in.Body); err != nil {
		httpx.WriteError(w, err)
		return
	}
	var slug string
	_ = h.Service.DB.QueryRowContext(r.Context(), `SELECT p.slug FROM issues i JOIN projects p ON p.id=i.project_id WHERE i.id=?`, issueID).Scan(&slug)
	var number int
	_ = h.Service.DB.QueryRowContext(r.Context(), `SELECT number FROM issues WHERE id=?`, issueID).Scan(&number)
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/projects/"+slug+"/issues/"+strconvI(number), http.StatusSeeOther)
		return
	}
	httpx.WriteMessage(w, http.StatusCreated, "comment added")
}

func (h *Handler) delete(w http.ResponseWriter, r *http.Request) {
	u, ok := auth.UserFromContext(r.Context())
	if !ok {
		httpx.WriteError(w, httpx.Unauthorized("authentication required"))
		return
	}
	id, err := httpx.ParseID(chi.URLParam(r, "id"))
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	if err := h.Service.Delete(r.Context(), id, u.ID); err != nil {
		httpx.WriteError(w, err)
		return
	}
	var slug string
	var number int
	_ = h.Service.DB.QueryRowContext(r.Context(), `SELECT p.slug, i.number FROM comments c JOIN issues i ON i.id=c.issue_id JOIN projects p ON p.id=i.project_id WHERE c.id=?`, id).Scan(&slug, &number)
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/projects/"+slug+"/issues/"+strconvI(number), http.StatusSeeOther)
		return
	}
	httpx.WriteMessage(w, http.StatusOK, "comment removed")
}

func strconvI(i int) string {
	return strconv.Itoa(i)
}
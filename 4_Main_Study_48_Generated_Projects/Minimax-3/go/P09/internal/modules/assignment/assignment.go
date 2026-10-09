package assignment

import (
	"context"
	"database/sql"
	"net/http"

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

type Handler struct {
	Service *Service
}

func NewHandler(s *Service) *Handler { return &Handler{Service: s} }

func (h *Handler) Routes(r chi.Router, tpl *templates.Engine) {
	r.Get("/api/issue/assignment_and_workflow", h.list)
	r.Post("/api/issue/assignment_and_workflow/{issueID}", h.apply)
}

type assignment struct {
	ID             int64   `json:"id"`
	IssueID        int64   `json:"issue_id"`
	ActorID        int64   `json:"actor_id"`
	ActorUsername  string  `json:"actor_username"`
	OldAssigneeID  *int64  `json:"old_assignee_id"`
	NewAssigneeID  *int64  `json:"new_assignee_id"`
	OldState       string  `json:"old_state"`
	NewState       string  `json:"new_state"`
	OldMilestoneID *int64  `json:"old_milestone_id"`
	NewMilestoneID *int64  `json:"new_milestone_id"`
	CreatedAt      string  `json:"created_at"`
}

func (h *Handler) list(w http.ResponseWriter, r *http.Request) {
	rows, err := h.Service.DB.QueryContext(r.Context(), `SELECT a.id, a.issue_id, a.actor_id, u.username, a.old_assignee_id, a.new_assignee_id, a.old_state, a.new_state, a.old_milestone_id, a.new_milestone_id, a.created_at FROM assignments a JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 100`)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	defer rows.Close()
	var out []assignment
	for rows.Next() {
		var a assignment
		if err := rows.Scan(&a.ID, &a.IssueID, &a.ActorID, &a.ActorUsername, &a.OldAssigneeID, &a.NewAssigneeID, &a.OldState, &a.NewState, &a.OldMilestoneID, &a.NewMilestoneID, &a.CreatedAt); err != nil {
			httpx.WriteError(w, err)
			return
		}
		out = append(out, a)
	}
	httpx.WriteJSON(w, http.StatusOK, out)
}

type applyPayload struct {
	State       string `json:"state"`
	AssigneeID  *int64 `json:"assignee_id"`
	MilestoneID *int64 `json:"milestone_id"`
}

func (h *Handler) apply(w http.ResponseWriter, r *http.Request) {
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
	var in applyPayload
	if err := r.ParseForm(); err == nil {
		in.State = r.FormValue("state")
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
	} else if err := httpx.ReadJSON(r, &in); err != nil {
		httpx.WriteError(w, httpx.BadRequest("invalid input"))
		return
	}
	var projectID int64
	_ = h.Service.DB.QueryRowContext(r.Context(), `SELECT project_id FROM issues WHERE id=?`, issueID).Scan(&projectID)
	if projectID == 0 {
		httpx.WriteError(w, httpx.NotFound("issue not found"))
		return
	}
	if !canManageWorkflow(r.Context(), h.Service.DB, u, projectID) {
		httpx.WriteError(w, httpx.Forbidden("not allowed"))
		return
	}
	var oldState string
	var oldAssignee sql.NullInt64
	if err := h.Service.DB.QueryRowContext(r.Context(), `SELECT state, assignee_id FROM issues WHERE id=?`, issueID).Scan(&oldState, &oldAssignee); err != nil {
		httpx.WriteError(w, httpx.NotFound("issue not found"))
		return
	}
	newState := in.State
	if newState == "" {
		newState = oldState
	}
	if newState != "open" && newState != "closed" {
		httpx.WriteError(w, httpx.BadRequest("invalid state transition"))
		return
	}
	tx, err := h.Service.DB.BeginTx(r.Context(), nil)
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	defer tx.Rollback()
	newAssignee := oldAssignee
	if in.AssigneeID != nil {
		newAssignee = sql.NullInt64{Int64: *in.AssigneeID, Valid: true}
	}
	var newMilestone sql.NullInt64
	if in.MilestoneID != nil {
		newMilestone = sql.NullInt64{Int64: *in.MilestoneID, Valid: true}
	}
	if newState == "closed" {
		_, err = tx.ExecContext(r.Context(), `UPDATE issues SET state=?, assignee_id=?, milestone_id=?, closed_at=COALESCE(closed_at, datetime('now')), updated_at=datetime('now') WHERE id=?`, newState, newAssignee, newMilestone, issueID)
	} else {
		_, err = tx.ExecContext(r.Context(), `UPDATE issues SET state=?, assignee_id=?, milestone_id=?, closed_at=NULL, updated_at=datetime('now') WHERE id=?`, newState, newAssignee, newMilestone, issueID)
	}
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	_, err = tx.ExecContext(r.Context(), `INSERT INTO assignments(issue_id, actor_id, old_assignee_id, new_assignee_id, old_state, new_state, old_milestone_id, new_milestone_id) VALUES (?,?,?,?,?,?,?,?)`, issueID, u.ID, nullPtr(oldAssignee), nullPtr(newAssignee), oldState, newState, nil, nullPtr(newMilestone))
	if err != nil {
		httpx.WriteError(w, err)
		return
	}
	if err := tx.Commit(); err != nil {
		httpx.WriteError(w, err)
		return
	}
	if h.Service.Hub != nil {
		eventName := "issue.updated"
		if oldState != newState {
			if newState == "closed" {
				eventName = "issue.closed"
			} else {
				eventName = "issue.reopened"
			}
		}
		h.Service.Hub.Publish(realtime.Event{Event: eventName, Message: "issue state -> " + newState, IssueID: issueID, ActorID: u.ID})
	}
	_, _ = h.Service.DB.ExecContext(r.Context(), `INSERT INTO audit_events(actor_id, action, target_type, target_id, detail) VALUES (?, 'issue.workflow', 'issue', ?, ?)`, u.ID, issueID, newState)
	var slug string
	var number int
	_ = h.Service.DB.QueryRowContext(r.Context(), `SELECT p.slug, i.number FROM issues i JOIN projects p ON p.id=i.project_id WHERE i.id=?`, issueID).Scan(&slug, &number)
	if r.Header.Get("HX-Request") == "true" || r.Header.Get("Accept") == "text/html" {
		http.Redirect(w, r, "/projects/"+slug+"/issues/"+itoa(number), http.StatusSeeOther)
		return
	}
	httpx.WriteMessage(w, http.StatusOK, "workflow applied")
}

func itoa(i int) string {
	if i == 0 {
		return "0"
	}
	neg := i < 0
	if neg {
		i = -i
	}
	buf := []byte{}
	for i > 0 {
		buf = append([]byte{byte('0' + i%10)}, buf...)
		i /= 10
	}
	if neg {
		buf = append([]byte{'-'}, buf...)
	}
	return string(buf)
}

func nullPtr(n sql.NullInt64) any {
	if !n.Valid {
		return nil
	}
	return n.Int64
}

func canManageWorkflow(ctx context.Context, db *sql.DB, u *models.User, projectID int64) bool {
	if u == nil {
		return false
	}
	var role string
	_ = db.QueryRowContext(ctx, `SELECT role FROM project_members WHERE project_id=? AND user_id=?`, projectID, u.ID).Scan(&role)
	if role == "maintainer" {
		return true
	}
	var ownerID int64
	_ = db.QueryRowContext(ctx, `SELECT owner_id FROM projects WHERE id=?`, projectID).Scan(&ownerID)
	if ownerID == u.ID {
		return true
	}
	if u.Role == models.RoleAdmin {
		return true
	}
	return false
}
package projects

import (
	"context"
	"database/sql"

	"github.com/anomaly/p09/internal/httpx"
	"github.com/anomaly/p09/internal/models"
)

type sqlQuerier = sql.DB

type userBrief struct {
	ID       int64  `json:"id"`
	Username string `json:"username"`
	Role     string `json:"role"`
}

type issueBrief struct {
	ID                int64
	Number            int
	Title             string
	State             string
	Priority          string
	ProjectSlug       string
	ProjectName       string
	AssigneeUsername  string
	AssigneeID        *int64
	MilestoneTitle    string
	MilestoneID       *int64
	Labels            []labelBrief
	AuthorUsername    string
	AuthorID          int64
	CreatedAt         string
	UpdatedAt         string
	ClosedAt          string
	Body              string
	ProjectID         int64
}

type labelBrief struct {
	ID    int64  `json:"id"`
	Name  string `json:"name"`
	Color string `json:"color"`
}

func listIssuesForProject(ctx context.Context, db *sqlQuerier, projectID, _ int64, _ bool, limit int) ([]*issueBrief, error) {
	rows, err := db.QueryContext(ctx, `SELECT i.id, i.number, i.title, i.state, i.priority, i.author_id, i.assignee_id, i.milestone_id, i.created_at, i.updated_at, COALESCE(i.closed_at, ''), i.body, p.slug, p.name FROM issues i JOIN projects p ON p.id = i.project_id WHERE i.project_id=? ORDER BY i.number DESC LIMIT ?`, projectID, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []*issueBrief
	for rows.Next() {
		iss := &issueBrief{}
		if err := rows.Scan(&iss.ID, &iss.Number, &iss.Title, &iss.State, &iss.Priority, &iss.AuthorID, &iss.AssigneeID, &iss.MilestoneID, &iss.CreatedAt, &iss.UpdatedAt, &iss.ClosedAt, &iss.Body, &iss.ProjectSlug, &iss.ProjectName); err != nil {
			return nil, err
		}
		iss.ProjectID = projectID
		out = append(out, iss)
	}
	for _, iss := range out {
		labels, _ := labelsForIssue(ctx, db, iss.ID)
		iss.Labels = labels
	}
	if err := rows.Err(); err != nil {
		return nil, err
	}
	for _, iss := range out {
		if iss.AuthorID != 0 {
			var username string
			_ = db.QueryRowContext(ctx, `SELECT username FROM users WHERE id=?`, iss.AuthorID).Scan(&username)
			iss.AuthorUsername = username
		}
		if iss.AssigneeID != nil && *iss.AssigneeID != 0 {
			var username string
			_ = db.QueryRowContext(ctx, `SELECT username FROM users WHERE id=?`, *iss.AssigneeID).Scan(&username)
			iss.AssigneeUsername = username
		}
		if iss.MilestoneID != nil && *iss.MilestoneID != 0 {
			var title string
			_ = db.QueryRowContext(ctx, `SELECT title FROM milestones WHERE id=?`, *iss.MilestoneID).Scan(&title)
			iss.MilestoneTitle = title
		}
	}
	return out, nil
}

func labelsForIssue(ctx context.Context, db *sqlQuerier, issueID int64) ([]labelBrief, error) {
	rows, err := db.QueryContext(ctx, `SELECT l.id, l.name, l.color FROM labels l JOIN issue_labels il ON il.label_id=l.id WHERE il.issue_id=? ORDER BY l.name`, issueID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []labelBrief
	for rows.Next() {
		var l labelBrief
		if err := rows.Scan(&l.ID, &l.Name, &l.Color); err != nil {
			return nil, err
		}
		out = append(out, l)
	}
	return out, rows.Err()
}

type webhookBrief struct {
	ID     int64  `json:"id"`
	URL    string `json:"url"`
	Events string `json:"events"`
	Active bool   `json:"active"`
}

func listWebhooksForProject(ctx context.Context, db *sqlQuerier, projectID int64) ([]*webhookBrief, error) {
	rows, err := db.QueryContext(ctx, `SELECT id, url, events, active FROM webhooks WHERE project_id=? ORDER BY id DESC`, projectID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []*webhookBrief
	for rows.Next() {
		w := &webhookBrief{}
		var active int
		if err := rows.Scan(&w.ID, &w.URL, &w.Events, &active); err != nil {
			return nil, err
		}
		w.Active = active != 0
		out = append(out, w)
	}
	return out, rows.Err()
}

func countProjects(ctx context.Context, db *sqlQuerier) (int, error) {
	var n int
	err := db.QueryRowContext(ctx, `SELECT COUNT(*) FROM projects`).Scan(&n)
	return n, err
}

func countIssues(ctx context.Context, db *sqlQuerier) (int, error) {
	var n int
	err := db.QueryRowContext(ctx, `SELECT COUNT(*) FROM issues`).Scan(&n)
	return n, err
}

func countUsers(ctx context.Context, db *sqlQuerier) (int, error) {
	var n int
	err := db.QueryRowContext(ctx, `SELECT COUNT(*) FROM users`).Scan(&n)
	return n, err
}

func countComments(ctx context.Context, db *sqlQuerier) (int, error) {
	var n int
	err := db.QueryRowContext(ctx, `SELECT COUNT(*) FROM comments`).Scan(&n)
	return n, err
}

func loadSetting(ctx context.Context, db *sqlQuerier, key string) string {
	var v string
	err := db.QueryRowContext(ctx, `SELECT value FROM settings WHERE key=?`, key).Scan(&v)
	if err != nil {
		return ""
	}
	return v
}

func saveSetting(ctx context.Context, db *sqlQuerier, key, value string) error {
	_, err := db.ExecContext(ctx, `INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=datetime('now')`, key, value)
	return err
}

func ensureRole(role string) string {
	switch role {
	case models.RoleAdmin, models.RoleMaintainer, models.RoleDeveloper, models.RoleReporter, models.RoleMember:
		return role
	}
	return models.RoleDeveloper
}

var _ = httpx.BadRequest
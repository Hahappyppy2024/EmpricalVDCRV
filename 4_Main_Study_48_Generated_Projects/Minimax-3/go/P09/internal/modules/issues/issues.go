package issues

import (
	"context"
	"database/sql"
	"errors"
	"strings"
	"time"

	"github.com/anomaly/p09/internal/httpx"
	"github.com/anomaly/p09/internal/models"
)

type Service struct {
	DB *sql.DB
}

func NewService(db *sql.DB) *Service { return &Service{DB: db} }

type Issue struct {
	models.Issue
	ProjectSlug       string `json:"project_slug"`
	ProjectName       string `json:"project_name"`
	AuthorUsername    string `json:"author_username"`
	AssigneeUsername  string `json:"assignee_username,omitempty"`
	MilestoneTitle    string `json:"milestone_title,omitempty"`
	Labels            []Label `json:"labels"`
}

type Label struct {
	ID    int64  `json:"id"`
	Name  string `json:"name"`
	Color string `json:"color"`
}

func (s *Service) Create(ctx context.Context, projectID, authorID int64, title, body, priority string, assigneeID, milestoneID *int64, labelIDs []int64) (*Issue, error) {
	title = strings.TrimSpace(title)
	if title == "" {
		return nil, httpx.BadRequest("title is required")
	}
	if priority == "" {
		priority = models.PriorityNormal
	}
	if !validPriority(priority) {
		return nil, httpx.BadRequest("invalid priority")
	}
	var number int
	if err := s.DB.QueryRowContext(ctx, `SELECT COALESCE(MAX(number),0)+1 FROM issues WHERE project_id=?`, projectID).Scan(&number); err != nil {
		return nil, err
	}
	res, err := s.DB.ExecContext(ctx, `INSERT INTO issues(project_id, number, title, body, priority, state, author_id, assignee_id, milestone_id) VALUES (?,?,?,?,?, 'open', ?, ?, ?)`, projectID, number, title, body, priority, authorID, assigneeID, milestoneID)
	if err != nil {
		return nil, err
	}
	id, _ := res.LastInsertId()
	for _, lid := range labelIDs {
		_, _ = s.DB.ExecContext(ctx, `INSERT OR IGNORE INTO issue_labels(issue_id, label_id) VALUES (?,?)`, id, lid)
	}
	_, _ = s.DB.ExecContext(ctx, `INSERT INTO assignments(issue_id, actor_id, new_assignee_id, new_milestone_id, new_state) VALUES (?,?,?,?, 'open')`, id, authorID, assigneeID, milestoneID)
	return s.Get(ctx, id)
}

func (s *Service) Get(ctx context.Context, id int64) (*Issue, error) {
	row := s.DB.QueryRowContext(ctx, `SELECT i.id, i.project_id, i.number, i.title, i.body, i.priority, i.state, i.author_id, i.assignee_id, i.milestone_id, i.created_at, i.updated_at, COALESCE(i.closed_at, ''), p.slug, p.name FROM issues i JOIN projects p ON p.id = i.project_id WHERE i.id=?`, id)
	iss := &Issue{}
	var created, updated string
	var closedAt sql.NullString
	if err := row.Scan(&iss.ID, &iss.ProjectID, &iss.Number, &iss.Title, &iss.Body, &iss.Priority, &iss.State, &iss.AuthorID, &iss.AssigneeID, &iss.MilestoneID, &created, &updated, &closedAt, &iss.ProjectSlug, &iss.ProjectName); err != nil {
		return nil, httpx.NotFound("issue not found")
	}
	if closedAt.Valid {
		t, err := time.Parse("2006-01-02 15:04:05", closedAt.String)
		if err == nil {
			iss.ClosedAt = &t
		}
	}
	iss.CreatedAt, _ = time.Parse("2006-01-02 15:04:05", created)
	iss.UpdatedAt, _ = time.Parse("2006-01-02 15:04:05", updated)
	if iss.AuthorID != 0 {
		_ = s.DB.QueryRowContext(ctx, `SELECT username FROM users WHERE id=?`, iss.AuthorID).Scan(&iss.AuthorUsername)
	}
	if iss.AssigneeID != nil && *iss.AssigneeID != 0 {
		_ = s.DB.QueryRowContext(ctx, `SELECT username FROM users WHERE id=?`, *iss.AssigneeID).Scan(&iss.AssigneeUsername)
	}
	if iss.MilestoneID != nil && *iss.MilestoneID != 0 {
		_ = s.DB.QueryRowContext(ctx, `SELECT title FROM milestones WHERE id=?`, *iss.MilestoneID).Scan(&iss.MilestoneTitle)
	}
	labels, _ := s.labels(ctx, id)
	iss.Labels = labels
	return iss, nil
}

func (s *Service) GetByNumber(ctx context.Context, projectSlug string, number int) (*Issue, error) {
	var id int64
	if err := s.DB.QueryRowContext(ctx, `SELECT i.id FROM issues i JOIN projects p ON p.id = i.project_id WHERE p.slug=? AND i.number=?`, projectSlug, number).Scan(&id); err != nil {
		return nil, httpx.NotFound("issue not found")
	}
	return s.Get(ctx, id)
}

func (s *Service) labels(ctx context.Context, issueID int64) ([]Label, error) {
	rows, err := s.DB.QueryContext(ctx, `SELECT l.id, l.name, l.color FROM labels l JOIN issue_labels il ON il.label_id = l.id WHERE il.issue_id = ? ORDER BY l.name`, issueID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []Label
	for rows.Next() {
		l := Label{}
		if err := rows.Scan(&l.ID, &l.Name, &l.Color); err != nil {
			return nil, err
		}
		out = append(out, l)
	}
	return out, rows.Err()
}

func validPriority(p string) bool {
	switch p {
	case models.PriorityLow, models.PriorityNormal, models.PriorityHigh, models.PriorityUrgent:
		return true
	}
	return false
}

func validState(s string) bool {
	return s == models.IssueStateOpen || s == models.IssueStateClosed
}

type WorkflowInput struct {
	State        string
	AssigneeID   *int64
	MilestoneID  *int64
}

func (s *Service) ApplyWorkflow(ctx context.Context, actorID, issueID int64, in WorkflowInput) (*Issue, error) {
	if in.State != "" && !validState(in.State) {
		return nil, httpx.BadRequest("invalid state")
	}
	tx, err := s.DB.BeginTx(ctx, nil)
	if err != nil {
		return nil, err
	}
	defer tx.Rollback()
	var oldState string
	var oldAssignee sql.NullInt64
	var oldMilestone sql.NullInt64
	if err := tx.QueryRowContext(ctx, `SELECT state, assignee_id, milestone_id FROM issues WHERE id=?`, issueID).Scan(&oldState, &oldAssignee, &oldMilestone); err != nil {
		return nil, httpx.NotFound("issue not found")
	}
	newState := oldState
	if in.State != "" {
		newState = in.State
	}
	newAssignee := oldAssignee
	if in.AssigneeID != nil {
		newAssignee = sql.NullInt64{Int64: *in.AssigneeID, Valid: *in.AssigneeID != 0 || true}
	}
	newMilestone := oldMilestone
	if in.MilestoneID != nil {
		newMilestone = sql.NullInt64{Int64: *in.MilestoneID, Valid: true}
	}
	if newState == models.IssueStateClosed {
		_, err = tx.ExecContext(ctx, `UPDATE issues SET state=?, assignee_id=?, milestone_id=?, closed_at=COALESCE(closed_at, datetime('now')), updated_at=datetime('now') WHERE id=?`, newState, newAssignee, newMilestone, issueID)
	} else {
		_, err = tx.ExecContext(ctx, `UPDATE issues SET state=?, assignee_id=?, milestone_id=?, closed_at=NULL, updated_at=datetime('now') WHERE id=?`, newState, newAssignee, newMilestone, issueID)
	}
	if err != nil {
		return nil, err
	}
	_, err = tx.ExecContext(ctx, `INSERT INTO assignments(issue_id, actor_id, old_assignee_id, new_assignee_id, old_state, new_state, old_milestone_id, new_milestone_id) VALUES (?,?,?,?,?,?,?,?)`, issueID, actorID, nullInt64Ptr(oldAssignee), nullInt64Ptr(newAssignee), oldState, newState, nullInt64Ptr(oldMilestone), nullInt64Ptr(newMilestone))
	if err != nil {
		return nil, err
	}
	if err := tx.Commit(); err != nil {
		return nil, err
	}
	return s.Get(ctx, issueID)
}

func nullInt64Ptr(n sql.NullInt64) any {
	if !n.Valid {
		return nil
	}
	return n.Int64
}

type Comment struct {
	models.Comment
	AuthorUsername string `json:"author_username"`
}

func (s *Service) ListComments(ctx context.Context, issueID int64) ([]*Comment, error) {
	rows, err := s.DB.QueryContext(ctx, `SELECT c.id, c.issue_id, c.author_id, c.body, c.created_at, c.updated_at, u.username FROM comments c JOIN users u ON u.id=c.author_id WHERE c.issue_id=? ORDER BY c.id`, issueID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []*Comment
	for rows.Next() {
		c := &Comment{}
		var created, updated string
		if err := rows.Scan(&c.ID, &c.IssueID, &c.AuthorID, &c.Body, &created, &updated, &c.AuthorUsername); err != nil {
			return nil, err
		}
		c.CreatedAt, _ = time.Parse("2006-01-02 15:04:05", created)
		c.UpdatedAt, _ = time.Parse("2006-01-02 15:04:05", updated)
		out = append(out, c)
	}
	return out, rows.Err()
}

type Attachment struct {
	models.Attachment
	UploaderUsername string `json:"uploader_username"`
}

func (s *Service) ListAttachments(ctx context.Context, issueID int64) ([]*Attachment, error) {
	rows, err := s.DB.QueryContext(ctx, `SELECT a.id, a.issue_id, a.uploader_id, a.filename, a.stored_path, a.mime_type, a.size, a.created_at, u.username FROM attachments a JOIN users u ON u.id=a.uploader_id WHERE a.issue_id=? ORDER BY a.id`, issueID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []*Attachment
	for rows.Next() {
		a := &Attachment{}
		var created string
		if err := rows.Scan(&a.ID, &a.IssueID, &a.UploaderID, &a.Filename, &a.StoredPath, &a.MimeType, &a.Size, &created, &a.UploaderUsername); err != nil {
			return nil, err
		}
		a.CreatedAt, _ = time.Parse("2006-01-02 15:04:05", created)
		out = append(out, a)
	}
	return out, rows.Err()
}

func (s *Service) ProjectIDForIssue(ctx context.Context, issueID int64) (int64, error) {
	var id int64
	if err := s.DB.QueryRowContext(ctx, `SELECT project_id FROM issues WHERE id=?`, issueID).Scan(&id); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return 0, httpx.NotFound("issue not found")
		}
		return 0, err
	}
	return id, nil
}
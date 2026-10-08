package store

import (
	"database/sql"
	"errors"
	"fmt"
	"strings"

	"issuetracker/internal/models"
)

// Issues ---------------------------------------------------------------------

// NextIssueNumber returns the next issue number for a project.
func (s *Store) NextIssueNumber(projectID int64) (int, error) {
	var n sql.NullInt64
	err := s.DB.QueryRow(`SELECT MAX(number) FROM issues WHERE project_id=?`, projectID).Scan(&n)
	if err != nil {
		return 1, err
	}
	if !n.Valid {
		return 1, nil
	}
	return int(n.Int64) + 1, nil
}

func (s *Store) CreateIssue(i *models.Issue) (*models.Issue, error) {
	num, err := s.NextIssueNumber(i.ProjectID)
	if err != nil {
		return nil, err
	}
	now := Now()
	var mid, aid any
	if i.MilestoneID != nil {
		mid = *i.MilestoneID
	}
	if i.AssigneeID != nil {
		aid = *i.AssigneeID
	}
	res, err := s.DB.Exec(`INSERT INTO issues(project_id,number,title,body,priority,status,milestone_id,created_by,assignee_id,created_at,updated_at)
		VALUES(?,?,?,?,?,?,?,?,?,?,?)`, i.ProjectID, num, i.Title, i.Body, i.Priority, i.Status, mid, i.CreatedBy, aid, now, now)
	if err != nil {
		return nil, err
	}
	id, _ := res.LastInsertId()
	return s.IssueByID(id)
}

func (s *Store) IssueByID(id int64) (*models.Issue, error) {
	row := s.DB.QueryRow(`SELECT i.id,i.project_id,p.slug,i.number,i.title,i.body,i.priority,i.status,i.milestone_id,i.created_by,
		cu.username,i.assignee_id,COALESCE(au.username,''),i.created_at,i.updated_at
		FROM issues i
		JOIN projects p ON p.id=i.project_id
		JOIN users cu ON cu.id=i.created_by
		LEFT JOIN users au ON au.id=i.assignee_id
		WHERE i.id=?`, id)
	return scanIssue(row)
}

func (s *Store) UpdateIssue(id int64, fields map[string]any) error {
	if len(fields) == 0 {
		return nil
	}
	sets := make([]string, 0, len(fields))
	args := make([]any, 0, len(fields))
	for k, v := range fields {
		sets = append(sets, k+"=?")
		args = append(args, v)
	}
	args = append(args, id)
	_, err := s.DB.Exec(`UPDATE issues SET `+strings.Join(sets, ",")+`, updated_at=`+fmt.Sprintf("'%s'", Now())+` WHERE id=?`, args...)
	return err
}

// IssueSearchParams carries the supported filters for issue search.
type IssueSearchParams struct {
	Query       string
	Status      string
	Priority    string
	ProjectID   int64
	AssigneeID  int64
	CreatorID   int64
	MilestoneID int64
	LabelID     int64
	Limit       int
}

// SearchIssues returns visible issues matching the filters. For private
// projects the user must have access; such issues are excluded otherwise.
func (s *Store) SearchIssues(userID int64, p IssueSearchParams) ([]models.Issue, error) {
	where := []string{`(p.visibility='public' OR p.owner_id=? OR EXISTS (SELECT 1 FROM project_members m WHERE m.project_id=p.id AND m.user_id=?))`}
	args := []any{userID, userID}
	if p.Query != "" {
		where = append(where, `(i.title LIKE ? OR i.body LIKE ?)`)
		args = append(args, "%"+p.Query+"%", "%"+p.Query+"%")
	}
	if p.Status != "" {
		where = append(where, "i.status=?")
		args = append(args, p.Status)
	}
	if p.Priority != "" {
		where = append(where, "i.priority=?")
		args = append(args, p.Priority)
	}
	if p.ProjectID != 0 {
		where = append(where, "i.project_id=?")
		args = append(args, p.ProjectID)
	}
	if p.AssigneeID != 0 {
		where = append(where, "i.assignee_id=?")
		args = append(args, p.AssigneeID)
	}
	if p.CreatorID != 0 {
		where = append(where, "i.created_by=?")
		args = append(args, p.CreatorID)
	}
	if p.MilestoneID != 0 {
		where = append(where, "i.milestone_id=?")
		args = append(args, p.MilestoneID)
	}
	if p.LabelID != 0 {
		where = append(where, "EXISTS (SELECT 1 FROM issue_labels il WHERE il.issue_id=i.id AND il.label_id=?)")
		args = append(args, p.LabelID)
	}
	limit := p.Limit
	if limit <= 0 || limit > 500 {
		limit = 100
	}
	query := `SELECT i.id,i.project_id,p.slug,i.number,i.title,i.body,i.priority,i.status,i.milestone_id,i.created_by,
		cu.username,i.assignee_id,COALESCE(au.username,''),i.created_at,i.updated_at
		FROM issues i
		JOIN projects p ON p.id=i.project_id
		JOIN users cu ON cu.id=i.created_by
		LEFT JOIN users au ON au.id=i.assignee_id
		WHERE ` + strings.Join(where, " AND ") + ` ORDER BY i.id DESC LIMIT ` + fmt.Sprint(limit)
	rows, err := s.DB.Query(query, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Issue
	for rows.Next() {
		it, err := scanIssue(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, *it)
	}
	return out, rows.Err()
}

// IssueLabels returns the label names for an issue.
func (s *Store) IssueLabels(issueID int64) ([]models.Label, error) {
	rows, err := s.DB.Query(`SELECT l.id,l.project_id,l.name,l.color FROM issue_labels il JOIN labels l ON l.id=il.label_id WHERE il.issue_id=? ORDER BY l.id`, issueID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Label
	for rows.Next() {
		var l models.Label
		if err := rows.Scan(&l.ID, &l.ProjectID, &l.Name, &l.Color); err != nil {
			return nil, err
		}
		out = append(out, l)
	}
	return out, rows.Err()
}

// SetIssueLabels replaces the label set of an issue.
func (s *Store) SetIssueLabels(issueID int64, labelIDs []int64) error {
	if _, err := s.DB.Exec(`DELETE FROM issue_labels WHERE issue_id=?`, issueID); err != nil {
		return err
	}
	for _, lid := range labelIDs {
		if _, err := s.DB.Exec(`INSERT OR IGNORE INTO issue_labels(issue_id,label_id) VALUES(?,?)`, issueID, lid); err != nil {
			return err
		}
	}
	return nil
}

func scanIssue(r scanner) (*models.Issue, error) {
	var i models.Issue
	var milestone, assignee sql.NullInt64
	var created, updated string
	err := r.Scan(&i.ID, &i.ProjectID, &i.ProjectSlug, &i.Number, &i.Title, &i.Body, &i.Priority, &i.Status,
		&milestone, &i.CreatedBy, &i.CreatorName, &assignee, &i.AssigneeName, &created, &updated)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, ErrNotFound
		}
		return nil, err
	}
	if milestone.Valid {
		i.MilestoneID = &milestone.Int64
	}
	if assignee.Valid {
		i.AssigneeID = &assignee.Int64
	}
	i.CreatedAt = parseTime(created)
	i.UpdatedAt = parseTime(updated)
	return &i, nil
}

// Comments -------------------------------------------------------------------

func (s *Store) CreateComment(issueID, authorID int64, body string) (*models.Comment, error) {
	now := Now()
	res, err := s.DB.Exec(`INSERT INTO comments(issue_id,author_id,body,created_at,updated_at) VALUES(?,?,?,?,?)`,
		issueID, authorID, body, now, now)
	if err != nil {
		return nil, err
	}
	id, _ := res.LastInsertId()
	return s.CommentByID(id)
}

func (s *Store) CommentByID(id int64) (*models.Comment, error) {
	row := s.DB.QueryRow(`SELECT c.id,c.issue_id,c.author_id,u.username,c.body,c.created_at,c.updated_at
		FROM comments c JOIN users u ON u.id=c.author_id WHERE c.id=?`, id)
	var c models.Comment
	var created, updated string
	err := row.Scan(&c.ID, &c.IssueID, &c.AuthorID, &c.AuthorName, &c.Body, &created, &updated)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, ErrNotFound
		}
		return nil, err
	}
	c.CreatedAt = parseTime(created)
	c.UpdatedAt = parseTime(updated)
	return &c, nil
}

func (s *Store) ListComments(issueID int64) ([]models.Comment, error) {
	rows, err := s.DB.Query(`SELECT c.id,c.issue_id,c.author_id,u.username,c.body,c.created_at,c.updated_at
		FROM comments c JOIN users u ON u.id=c.author_id WHERE c.issue_id=? ORDER BY c.id`, issueID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Comment
	for rows.Next() {
		var c models.Comment
		var created, updated string
		if err := rows.Scan(&c.ID, &c.IssueID, &c.AuthorID, &c.AuthorName, &c.Body, &created, &updated); err != nil {
			return nil, err
		}
		c.CreatedAt = parseTime(created)
		c.UpdatedAt = parseTime(updated)
		out = append(out, c)
	}
	return out, rows.Err()
}

func (s *Store) UpdateComment(id int64, body string) error {
	res, err := s.DB.Exec(`UPDATE comments SET body=?, updated_at=? WHERE id=?`, body, Now(), id)
	if err != nil {
		return err
	}
	if n, _ := res.RowsAffected(); n == 0 {
		return ErrNotFound
	}
	return nil
}

func (s *Store) DeleteComment(id int64) error {
	res, err := s.DB.Exec(`DELETE FROM comments WHERE id=?`, id)
	if err != nil {
		return err
	}
	if n, _ := res.RowsAffected(); n == 0 {
		return ErrNotFound
	}
	return nil
}

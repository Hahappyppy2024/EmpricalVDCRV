package projects

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"regexp"
	"strings"
	"time"

	"github.com/anomaly/p09/internal/httpx"
	"github.com/anomaly/p09/internal/models"
)

type Service struct {
	DB *sql.DB
}

func NewService(db *sql.DB) *Service { return &Service{DB: db} }

var slugRe = regexp.MustCompile(`^[a-z0-9][a-z0-9-]{1,60}$`)

func ValidateSlug(slug string) error {
	if !slugRe.MatchString(slug) {
		return errors.New("slug must be lowercase alphanumeric with dashes (3-61 chars)")
	}
	return nil
}

type Project struct {
	models.Project
	OwnerUsername string `json:"owner_username"`
	IssueCount    int    `json:"issue_count"`
}

func (s *Service) Create(ctx context.Context, ownerID int64, slug, name, description, visibility string) (*Project, error) {
	slug = strings.ToLower(strings.TrimSpace(slug))
	name = strings.TrimSpace(name)
	if err := ValidateSlug(slug); err != nil {
		return nil, httpx.BadRequest(err.Error())
	}
	if name == "" {
		return nil, httpx.BadRequest("name is required")
	}
	if visibility != models.VisibilityPublic && visibility != models.VisibilityPrivate {
		visibility = models.VisibilityPublic
	}
	res, err := s.DB.ExecContext(ctx, `INSERT INTO projects(slug, name, description, visibility, owner_id) VALUES (?,?,?,?,?)`, slug, name, description, visibility, ownerID)
	if err != nil {
		if strings.Contains(err.Error(), "UNIQUE") {
			return nil, httpx.Conflict("slug already exists")
		}
		return nil, err
	}
	id, _ := res.LastInsertId()
	_, _ = s.DB.ExecContext(ctx, `INSERT INTO project_members(project_id, user_id, role) VALUES (?,?, 'maintainer')`, id, ownerID)
	if visibility == models.VisibilityPrivate {
		_, _ = s.DB.ExecContext(ctx, `INSERT OR IGNORE INTO private_access(project_id, user_id, granted_by) VALUES (?,?,?)`, id, ownerID, ownerID)
	}
	_, _ = s.DB.ExecContext(ctx, `INSERT INTO audit_events(actor_id, action, target_type, target_id, detail) VALUES (?, 'project.create', 'project', ?, ?)`, ownerID, id, slug)
	return s.Get(ctx, id)
}

func (s *Service) Get(ctx context.Context, id int64) (*Project, error) {
	row := s.DB.QueryRowContext(ctx, `SELECT p.id, p.slug, p.name, p.description, p.visibility, p.owner_id, p.archived, p.created_at, p.updated_at, u.username FROM projects p JOIN users u ON u.id = p.owner_id WHERE p.id=?`, id)
	p := &Project{}
	var created, updated string
	var archived int
	if err := row.Scan(&p.ID, &p.Slug, &p.Name, &p.Description, &p.Visibility, &p.OwnerID, &archived, &created, &updated, &p.OwnerUsername); err != nil {
		return nil, httpx.NotFound("project not found")
	}
	p.Archived = archived != 0
	return p, nil
}

func (s *Service) GetBySlug(ctx context.Context, slug string) (*Project, error) {
	row := s.DB.QueryRowContext(ctx, `SELECT p.id, p.slug, p.name, p.description, p.visibility, p.owner_id, p.archived, p.created_at, p.updated_at, u.username FROM projects p JOIN users u ON u.id = p.owner_id WHERE p.slug=?`, slug)
	p := &Project{}
	var created, updated string
	var archived int
	if err := row.Scan(&p.ID, &p.Slug, &p.Name, &p.Description, &p.Visibility, &p.OwnerID, &archived, &created, &updated, &p.OwnerUsername); err != nil {
		return nil, httpx.NotFound("project not found")
	}
	p.Archived = archived != 0
	return p, nil
}

func (s *Service) List(ctx context.Context, userID int64, query string, limit, offset int) ([]*Project, int, error) {
	where := []string{"(p.visibility='public'"}
	args := []any{}
	if userID > 0 {
		where = append(where, fmt.Sprintf("OR p.owner_id=? OR p.id IN (SELECT project_id FROM project_members WHERE user_id=?) OR p.id IN (SELECT project_id FROM private_access WHERE user_id=?)"))
		args = append(args, userID, userID, userID)
	}
	where = append(where, ")")
	if query != "" {
		where = append(where, "(p.name LIKE ? OR p.slug LIKE ?)")
		like := "%" + query + "%"
		args = append(args, like, like)
	}
	q := `SELECT p.id, p.slug, p.name, p.description, p.visibility, p.owner_id, p.archived, p.created_at, p.updated_at, u.username, (SELECT COUNT(*) FROM issues i WHERE i.project_id=p.id) AS issue_count FROM projects p JOIN users u ON u.id=p.owner_id WHERE ` + strings.Join(where, " ") + ` ORDER BY p.created_at DESC LIMIT ? OFFSET ?`
	args = append(args, limit, offset)
	rows, err := s.DB.QueryContext(ctx, q, args...)
	if err != nil {
		return nil, 0, err
	}
	defer rows.Close()
	var out []*Project
	for rows.Next() {
		p := &Project{}
		var created, updated string
		var archived int
		if err := rows.Scan(&p.ID, &p.Slug, &p.Name, &p.Description, &p.Visibility, &p.OwnerID, &archived, &created, &updated, &p.OwnerUsername, &p.IssueCount); err != nil {
			return nil, 0, err
		}
		p.Archived = archived != 0
		out = append(out, p)
	}
	var total int
	countQ := `SELECT COUNT(*) FROM projects p WHERE ` + strings.Join(where, " ")
	if err := s.DB.QueryRowContext(ctx, countQ, args[:len(args)-2]...).Scan(&total); err != nil {
		return nil, 0, err
	}
	return out, total, nil
}

func (s *Service) Update(ctx context.Context, id int64, name, description, visibility string, archived bool) error {
	visibility = strings.TrimSpace(visibility)
	if visibility != models.VisibilityPublic && visibility != models.VisibilityPrivate {
		visibility = models.VisibilityPublic
	}
	arch := 0
	if archived {
		arch = 1
	}
	_, err := s.DB.ExecContext(ctx, `UPDATE projects SET name=?, description=?, visibility=?, archived=?, updated_at=datetime('now') WHERE id=?`, name, description, visibility, arch, id)
	return err
}

func (s *Service) CanManage(ctx context.Context, userID int64, projectID int64) bool {
	if userID == 0 {
		return false
	}
	var role string
	err := s.DB.QueryRowContext(ctx, `SELECT role FROM project_members WHERE project_id=? AND user_id=?`, projectID, userID).Scan(&role)
	if err == nil && (role == "maintainer" || role == "owner") {
		return true
	}
	var ownerID int64
	_ = s.DB.QueryRowContext(ctx, `SELECT owner_id FROM projects WHERE id=?`, projectID).Scan(&ownerID)
	if ownerID == userID {
		return true
	}
	var isAdmin string
	_ = s.DB.QueryRowContext(ctx, `SELECT role FROM users WHERE id=?`, userID).Scan(&isAdmin)
	return isAdmin == models.RoleAdmin
}

func (s *Service) CanView(ctx context.Context, userID int64, projectID int64) (bool, error) {
	var visibility string
	var ownerID int64
	if err := s.DB.QueryRowContext(ctx, `SELECT visibility, owner_id FROM projects WHERE id=?`, projectID).Scan(&visibility, &ownerID); err != nil {
		return false, err
	}
	if visibility == models.VisibilityPublic {
		return true, nil
	}
	if userID == 0 {
		return false, nil
	}
	if ownerID == userID {
		return true, nil
	}
	var memberCount int
	_ = s.DB.QueryRowContext(ctx, `SELECT COUNT(*) FROM project_members WHERE project_id=? AND user_id=?`, projectID, userID).Scan(&memberCount)
	if memberCount > 0 {
		return true, nil
	}
	var access int
	_ = s.DB.QueryRowContext(ctx, `SELECT COUNT(*) FROM private_access WHERE project_id=? AND user_id=?`, projectID, userID).Scan(&access)
	return access > 0, nil
}

type Label struct {
	ID        int64  `json:"id"`
	ProjectID int64  `json:"project_id"`
	Name      string `json:"name"`
	Color     string `json:"color"`
}

func (s *Service) ListLabels(ctx context.Context, projectID int64) ([]Label, error) {
	rows, err := s.DB.QueryContext(ctx, `SELECT id, project_id, name, color FROM labels WHERE project_id=? ORDER BY name`, projectID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []Label
	for rows.Next() {
		var l Label
		if err := rows.Scan(&l.ID, &l.ProjectID, &l.Name, &l.Color); err != nil {
			return nil, err
		}
		out = append(out, l)
	}
	return out, rows.Err()
}

func (s *Service) AddLabel(ctx context.Context, projectID int64, name, color string) error {
	name = strings.TrimSpace(name)
	if name == "" {
		return httpx.BadRequest("label name required")
	}
	if color == "" {
		color = "#6c757d"
	}
	_, err := s.DB.ExecContext(ctx, `INSERT INTO labels(project_id, name, color) VALUES (?,?,?)`, projectID, name, color)
	return err
}

type Milestone struct {
	models.Milestone
}

func (s *Service) ListMilestones(ctx context.Context, projectID int64) ([]Milestone, error) {
	rows, err := s.DB.QueryContext(ctx, `SELECT id, project_id, title, description, due_date, state FROM milestones WHERE project_id=? ORDER BY id`, projectID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []Milestone
	for rows.Next() {
		m := Milestone{}
		var due sql.NullString
		if err := rows.Scan(&m.ID, &m.ProjectID, &m.Title, &m.Description, &due, &m.State); err != nil {
			return nil, err
		}
		if due.Valid {
			t, err := time.Parse("2006-01-02 15:04:05", due.String)
			if err == nil {
				m.DueDate = &t
			}
		}
		out = append(out, m)
	}
	return out, rows.Err()
}

func (s *Service) AddMilestone(ctx context.Context, projectID int64, title, description, due string) error {
	title = strings.TrimSpace(title)
	if title == "" {
		return httpx.BadRequest("title required")
	}
	if due == "" {
		_, err := s.DB.ExecContext(ctx, `INSERT INTO milestones(project_id, title, description) VALUES (?,?,?)`, projectID, title, description)
		return err
	}
	_, err := s.DB.ExecContext(ctx, `INSERT INTO milestones(project_id, title, description, due_date) VALUES (?,?,?,?)`, projectID, title, description, due)
	return err
}

type Member struct {
	UserID   int64  `json:"user_id"`
	Username string `json:"username"`
	Role     string `json:"role"`
}

func (s *Service) ListMembers(ctx context.Context, projectID int64) ([]Member, error) {
	rows, err := s.DB.QueryContext(ctx, `SELECT pm.user_id, u.username, pm.role FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id=? ORDER BY pm.role DESC, u.username`, projectID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []Member
	for rows.Next() {
		m := Member{}
		if err := rows.Scan(&m.UserID, &m.Username, &m.Role); err != nil {
			return nil, err
		}
		out = append(out, m)
	}
	return out, rows.Err()
}

func (s *Service) AddMember(ctx context.Context, projectID int64, userID int64, role string) error {
	if role != "member" && role != "maintainer" {
		role = "member"
	}
	_, err := s.DB.ExecContext(ctx, `INSERT OR IGNORE INTO project_members(project_id, user_id, role) VALUES (?,?,?)`, projectID, userID, role)
	return err
}

type AuditSummary struct {
	ID        int64
	Action    string
	Detail    string
	CreatedAt string
}

func (s *Service) RecentAudits(ctx context.Context, projectID int64, limit int) ([]AuditSummary, error) {
	rows, err := s.DB.QueryContext(ctx, `SELECT id, action, detail, created_at FROM audit_events WHERE target_type='project' AND target_id=? ORDER BY id DESC LIMIT ?`, projectID, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []AuditSummary
	for rows.Next() {
		a := AuditSummary{}
		if err := rows.Scan(&a.ID, &a.Action, &a.Detail, &a.CreatedAt); err != nil {
			return nil, err
		}
		out = append(out, a)
	}
	return out, rows.Err()
}
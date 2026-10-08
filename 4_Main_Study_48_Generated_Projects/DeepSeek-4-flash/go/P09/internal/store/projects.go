package store

import (
	"database/sql"
	"errors"
	"strings"

	"issuetracker/internal/models"
)

// Projects -------------------------------------------------------------------

func (s *Store) CreateProject(p *models.Project) (*models.Project, error) {
	now := Now()
	res, err := s.DB.Exec(`INSERT INTO projects(name,slug,description,visibility,owner_id,created_at,updated_at)
		VALUES(?,?,?,?,?,?,?)`, p.Name, p.Slug, p.Description, p.Visibility, p.OwnerID, now, now)
	if err != nil {
		if strings.Contains(err.Error(), "UNIQUE") {
			return nil, ErrDuplicate
		}
		return nil, err
	}
	id, _ := res.LastInsertId()
	return s.ProjectByID(id)
}

func (s *Store) ProjectByID(id int64) (*models.Project, error) {
	row := s.DB.QueryRow(`SELECT p.id,p.name,p.slug,p.description,p.visibility,p.owner_id,u.username,p.created_at,p.updated_at
		FROM projects p JOIN users u ON u.id=p.owner_id WHERE p.id=?`, id)
	return scanProject(row)
}

func (s *Store) ProjectBySlug(slug string) (*models.Project, error) {
	row := s.DB.QueryRow(`SELECT p.id,p.name,p.slug,p.description,p.visibility,p.owner_id,u.username,p.created_at,p.updated_at
		FROM projects p JOIN users u ON u.id=p.owner_id WHERE p.slug=?`, slug)
	return scanProject(row)
}

func (s *Store) UpdateProject(id int64, name, desc, visibility string) error {
	res, err := s.DB.Exec(`UPDATE projects SET name=?, description=?, visibility=?, updated_at=? WHERE id=?`,
		name, desc, visibility, Now(), id)
	if err != nil {
		return err
	}
	if n, _ := res.RowsAffected(); n == 0 {
		return ErrNotFound
	}
	return nil
}

// ListVisibleProjects returns projects the user may see: public projects plus
// private projects the user owns or is a member of.
func (s *Store) ListVisibleProjects(userID int64) ([]models.Project, error) {
	rows, err := s.DB.Query(`SELECT DISTINCT p.id,p.name,p.slug,p.description,p.visibility,p.owner_id,u.username,p.created_at,p.updated_at
		FROM projects p JOIN users u ON u.id=p.owner_id
		WHERE p.visibility='public'
		   OR p.owner_id=?
		   OR EXISTS (SELECT 1 FROM project_members m WHERE m.project_id=p.id AND m.user_id=?)
		ORDER BY p.id`, userID, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	return scanProjects(rows)
}

func (s *Store) ListAllProjects() ([]models.Project, error) {
	rows, err := s.DB.Query(`SELECT p.id,p.name,p.slug,p.description,p.visibility,p.owner_id,u.username,p.created_at,p.updated_at
		FROM projects p JOIN users u ON u.id=p.owner_id ORDER BY p.id`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	return scanProjects(rows)
}

// CanAccessProject reports whether userID may view the given project.
func (s *Store) CanAccessProject(projectID, userID int64) bool {
	p, err := s.ProjectByID(projectID)
	if err != nil {
		return false
	}
	return p.Visibility == models.VisibilityPublic || s.IsProjectMember(projectID, userID) || s.IsProjectOwner(projectID, userID)
}

func (s *Store) IsProjectOwner(projectID, userID int64) bool {
	var one int
	err := s.DB.QueryRow(`SELECT 1 FROM projects WHERE id=? AND owner_id=?`, projectID, userID).Scan(&one)
	return err == nil
}

func (s *Store) IsProjectMember(projectID, userID int64) bool {
	var one int
	err := s.DB.QueryRow(`SELECT 1 FROM project_members WHERE project_id=? AND user_id=?`, projectID, userID).Scan(&one)
	return err == nil
}

// MemberRoleInProject returns the membership role or "".
func (s *Store) MemberRoleInProject(projectID, userID int64) string {
	var role string
	err := s.DB.QueryRow(`SELECT role FROM project_members WHERE project_id=? AND user_id=?`, projectID, userID).Scan(&role)
	if err != nil {
		return ""
	}
	return role
}

// AddProjectMember grants membership (used by the private_projects workflow).
func (s *Store) AddProjectMember(projectID, userID int64, role string) error {
	_, err := s.DB.Exec(`INSERT OR IGNORE INTO project_members(project_id,user_id,role) VALUES(?,?,?)`, projectID, userID, role)
	return err
}

func (s *Store) RemoveProjectMember(projectID, userID int64) error {
	_, err := s.DB.Exec(`DELETE FROM project_members WHERE project_id=? AND user_id=?`, projectID, userID)
	return err
}

func (s *Store) ListProjectMembers(projectID int64) ([]models.User, error) {
	rows, err := s.DB.Query(`SELECT u.id,u.username,u.email,u.display_name,u.password_hash,u.role,u.active,u.created_at,u.updated_at
		FROM project_members m JOIN users u ON u.id=m.user_id WHERE m.project_id=? ORDER BY u.username`, projectID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.User
	for rows.Next() {
		u, err := scanUser(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, *u)
	}
	return out, rows.Err()
}

func scanProject(r scanner) (*models.Project, error) {
	var p models.Project
	var created, updated string
	err := r.Scan(&p.ID, &p.Name, &p.Slug, &p.Description, &p.Visibility, &p.OwnerID, &p.OwnerName, &created, &updated)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, ErrNotFound
		}
		return nil, err
	}
	p.CreatedAt = parseTime(created)
	p.UpdatedAt = parseTime(updated)
	return &p, nil
}

func scanProjects(rows *sql.Rows) ([]models.Project, error) {
	var out []models.Project
	for rows.Next() {
		p, err := scanProject(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, *p)
	}
	return out, rows.Err()
}

// Labels and milestones ------------------------------------------------------

func (s *Store) CreateLabel(projectID int64, name, color string) (*models.Label, error) {
	res, err := s.DB.Exec(`INSERT INTO labels(project_id,name,color) VALUES(?,?,?)`, projectID, name, color)
	if err != nil {
		if strings.Contains(err.Error(), "UNIQUE") {
			return nil, ErrDuplicate
		}
		return nil, err
	}
	id, _ := res.LastInsertId()
	var l models.Label
	err = s.DB.QueryRow(`SELECT id,project_id,name,color FROM labels WHERE id=?`, id).Scan(&l.ID, &l.ProjectID, &l.Name, &l.Color)
	return &l, err
}

func (s *Store) ListLabels(projectID int64) ([]models.Label, error) {
	rows, err := s.DB.Query(`SELECT id,project_id,name,color FROM labels WHERE project_id=? ORDER BY id`, projectID)
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

func (s *Store) CreateMilestone(projectID int64, title string) (*models.Milestone, error) {
	res, err := s.DB.Exec(`INSERT INTO milestones(project_id,title,is_open,created_at) VALUES(?,?,1,?)`, projectID, title, Now())
	if err != nil {
		return nil, err
	}
	id, _ := res.LastInsertId()
	var m models.Milestone
	var isOpen int
	var created string
	err = s.DB.QueryRow(`SELECT id,project_id,title,is_open,created_at FROM milestones WHERE id=?`, id).Scan(&m.ID, &m.ProjectID, &m.Title, &isOpen, &created)
	m.IsOpen = isOpen == 1
	m.CreatedAt = parseTime(created)
	return &m, err
}

func (s *Store) ListMilestones(projectID int64) ([]models.Milestone, error) {
	rows, err := s.DB.Query(`SELECT id,project_id,title,is_open,created_at FROM milestones WHERE project_id=? ORDER BY id`, projectID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Milestone
	for rows.Next() {
		var m models.Milestone
		var isOpen int
		var created string
		if err := rows.Scan(&m.ID, &m.ProjectID, &m.Title, &isOpen, &created); err != nil {
			return nil, err
		}
		m.IsOpen = isOpen == 1
		m.CreatedAt = parseTime(created)
		out = append(out, m)
	}
	return out, rows.Err()
}

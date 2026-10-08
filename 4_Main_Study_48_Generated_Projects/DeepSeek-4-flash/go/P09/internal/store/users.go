package store

import (
	"database/sql"
	"errors"
	"time"

	"issuetracker/internal/models"
)

// User queries ---------------------------------------------------------------

func (s *Store) CreateUser(u *models.User) (*models.User, error) {
	now := Now()
	res, err := s.DB.Exec(`INSERT INTO users(username,email,display_name,password_hash,role,active,created_at,updated_at)
		VALUES(?,?,?,?,?,?,?,?)`, u.Username, u.Email, u.DisplayName, u.PasswordHash, u.Role, 1, now, now)
	if err != nil {
		return nil, err
	}
	id, _ := res.LastInsertId()
	return s.UserByID(id)
}

func (s *Store) UserByID(id int64) (*models.User, error) {
	row := s.DB.QueryRow(`SELECT id,username,email,display_name,password_hash,role,active,created_at,updated_at FROM users WHERE id=?`, id)
	return scanUser(row)
}

func (s *Store) UserByUsername(username string) (*models.User, error) {
	row := s.DB.QueryRow(`SELECT id,username,email,display_name,password_hash,role,active,created_at,updated_at FROM users WHERE username=?`, username)
	return scanUser(row)
}

func (s *Store) UserByEmail(email string) (*models.User, error) {
	row := s.DB.QueryRow(`SELECT id,username,email,display_name,password_hash,role,active,created_at,updated_at FROM users WHERE email=?`, email)
	return scanUser(row)
}

func (s *Store) ListUsers() ([]models.User, error) {
	rows, err := s.DB.Query(`SELECT id,username,email,display_name,password_hash,role,active,created_at,updated_at FROM users ORDER BY id`)
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

// UpdateUser applies role/active/display updates; blank fields keep values.
func (s *Store) UpdateUser(id int64, role string, active *bool, displayName string) error {
	u, err := s.UserByID(id)
	if err != nil {
		return err
	}
	if role != "" {
		u.Role = role
	}
	if active != nil {
		u.Active = *active
	}
	if displayName != "" {
		u.DisplayName = displayName
	}
	_, err = s.DB.Exec(`UPDATE users SET role=?,active=?,display_name=?,updated_at=? WHERE id=?`,
		u.Role, b2i(u.Active), u.DisplayName, Now(), id)
	return err
}

// TransferProjectOwnership moves a project to a new owner.
func (s *Store) TransferProjectOwnership(projectID, newOwnerID int64) error {
	res, err := s.DB.Exec(`UPDATE projects SET owner_id=?, updated_at=? WHERE id=?`, newOwnerID, Now(), projectID)
	if err != nil {
		return err
	}
	if n, _ := res.RowsAffected(); n == 0 {
		return ErrNotFound
	}
	return nil
}

type scanner interface{ Scan(dest ...any) error }

func scanUser(r scanner) (*models.User, error) {
	var u models.User
	var active int
	var created, updated string
	err := r.Scan(&u.ID, &u.Username, &u.Email, &u.DisplayName, &u.PasswordHash, &u.Role, &active, &created, &updated)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, ErrNotFound
		}
		return nil, err
	}
	u.Active = active == 1
	u.CreatedAt = parseTime(created)
	u.UpdatedAt = parseTime(updated)
	return &u, nil
}

// Sessions -------------------------------------------------------------------

func (s *Store) CreateSession(userID int64, token string, ttl time.Duration) error {
	_, err := s.DB.Exec(`INSERT INTO sessions(token,user_id,created_at,expires_at) VALUES(?,?,?,?)`,
		token, userID, Now(), formatTime(time.Now().Add(ttl)))
	return err
}

// SessionUser returns the user for a valid, unexpired session token.
func (s *Store) SessionUser(token string) (*models.User, error) {
	var userID int64
	var expiresAt string
	err := s.DB.QueryRow(`SELECT user_id, expires_at FROM sessions WHERE token=?`, token).Scan(&userID, &expiresAt)
	if err != nil {
		return nil, ErrNotFound
	}
	exp := parseTime(expiresAt)
	if !exp.IsZero() && time.Now().After(exp) {
		_, _ = s.DB.Exec(`DELETE FROM sessions WHERE token=?`, token)
		return nil, ErrNotFound
	}
	u, err := s.UserByID(userID)
	if err != nil {
		return nil, ErrNotFound
	}
	if !u.Active {
		return nil, ErrNotFound
	}
	return u, nil
}

func (s *Store) DeleteSession(token string) error {
	_, err := s.DB.Exec(`DELETE FROM sessions WHERE token=?`, token)
	return err
}

// Settings -------------------------------------------------------------------

func (s *Store) GetSetting(key, fallback string) string {
	var v string
	err := s.DB.QueryRow(`SELECT value FROM global_settings WHERE key=?`, key).Scan(&v)
	if err != nil {
		return fallback
	}
	return v
}

func (s *Store) SetSetting(key, value string) error {
	_, err := s.DB.Exec(`INSERT INTO global_settings(key,value) VALUES(?,?)
		ON CONFLICT(key) DO UPDATE SET value=excluded.value`, key, value)
	return err
}

func (s *Store) ListSettings() ([]models.GlobalSetting, error) {
	rows, err := s.DB.Query(`SELECT key,value FROM global_settings ORDER BY key`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.GlobalSetting
	for rows.Next() {
		var g models.GlobalSetting
		if err := rows.Scan(&g.Key, &g.Value); err != nil {
			return nil, err
		}
		out = append(out, g)
	}
	return out, rows.Err()
}

func b2i(b bool) int {
	if b {
		return 1
	}
	return 0
}

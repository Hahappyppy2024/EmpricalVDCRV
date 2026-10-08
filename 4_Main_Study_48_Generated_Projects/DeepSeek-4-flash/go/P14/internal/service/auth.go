package service

import (
	"database/sql"
	"regexp"
	"strconv"
	"time"

	"golang.org/x/crypto/bcrypt"

	"p14-agentic-platform/internal/models"
)

var usernameRe = regexp.MustCompile(`^[a-zA-Z0-9_]{3,20}$`)

// Register creates an account, records an account_access event and returns a
// session so the new user is signed in immediately (AGENT-01-FA-01).
func (s *Service) Register(username, email, password string) (*models.User, *models.Session, error) {
	if s.GetSetting("allow_registration", "true") != "true" {
		return nil, nil, forbidden("registration is disabled by governance")
	}
	if !usernameRe.MatchString(username) {
		return nil, nil, badRequest("username must be 3-20 chars of letters, digits or underscore")
	}
	if !validEmail(email) {
		return nil, nil, badRequest("a valid email address is required")
	}
	if len(password) < 6 {
		return nil, nil, badRequest("password must be at least 6 characters")
	}

	var exists int
	if err := s.repo.QueryRow("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?", username, email).Scan(&exists); err == nil && exists > 0 {
		return nil, nil, badRequest("username or email already in use")
	}

	hash, err := bcrypt.GenerateFromPassword([]byte(password), bcrypt.DefaultCost)
	if err != nil {
		return nil, nil, err
	}
	id, err := s.repo.Insert("users", []string{"username", "email", "password_hash", "role", "active", "created_at"},
		[]any{username, email, string(hash), "user", true, s.now()})
	if err != nil {
		return nil, nil, err
	}
	user, err := s.UserByID(id)
	if err != nil {
		return nil, nil, err
	}
	_, _ = s.repo.Insert("account_accesses", []string{"user_id", "account", "action", "status", "created_at"},
		[]any{user.ID, username, "register", "ok", s.now()})
	sess, err := s.CreateSession(user.ID)
	if err != nil {
		return nil, nil, err
	}
	s.Audit(user.ID, "auth.register", "users", id2str(id), "new account registered")
	return user, sess, nil
}

// Login authenticates a user, records the account_access event and returns a
// fresh session (AGENT-01-FA-02 rejects bad credentials deterministically).
func (s *Service) Login(username, password string) (*models.User, *models.Session, error) {
	row := s.repo.QueryRow("SELECT id, username, email, password_hash, role, active, created_at FROM users WHERE username = ? OR email = ?", username, username)
	u := &models.User{}
	err := row.Scan(&u.ID, &u.Username, &u.Email, &u.PasswordHash, &u.Role, &u.Active, &u.CreatedAt)
	if err != nil {
		s.recordRejected(username)
		return nil, nil, unauthorized("invalid username or password")
	}
	if bcrypt.CompareHashAndPassword([]byte(u.PasswordHash), []byte(password)) != nil {
		s.recordRejected(u.Username)
		return nil, nil, unauthorized("invalid username or password")
	}
	if !u.Active {
		s.recordRejected(u.Username)
		return nil, nil, forbidden("account is disabled by governance")
	}
	_, _ = s.repo.Insert("account_accesses", []string{"user_id", "account", "action", "status", "created_at"},
		[]any{u.ID, u.Username, "signin", "ok", s.now()})
	sess, err := s.CreateSession(u.ID)
	if err != nil {
		return nil, nil, err
	}
	return u, sess, nil
}

func (s *Service) recordRejected(account string) {
	var uid sql.NullInt64
	_ = s.repo.QueryRow("SELECT id FROM users WHERE username = ?", account).Scan(&uid)
	_, _ = s.repo.Insert("account_accesses", []string{"user_id", "account", "action", "status", "created_at"},
		[]any{uid.Int64, account, "signin", "rejected", s.now()})
}

// CreateSession persists a new server-side session.
func (s *Service) CreateSession(userID int64) (*models.Session, error) {
	token := randomHex(32)
	now := time.Now().UTC()
	expires := now.Add(time.Duration(s.cfg.SessionTTL) * time.Second)
	id, err := s.repo.Insert("sessions", []string{"token", "user_id", "created_at", "expires_at"},
		[]any{token, userID, now.Format(time.RFC3339), expires.Format(time.RFC3339)})
	if err != nil {
		return nil, err
	}
	s.purgeExpiredSessions()
	return &models.Session{ID: id, Token: token, UserID: userID, CreatedAt: now.Format(time.RFC3339), ExpiresAt: expires.Format(time.RFC3339)}, nil
}

// UserByToken resolves a session token to a user, deleting expired sessions.
func (s *Service) UserByToken(token string) (*models.User, error) {
	if token == "" {
		return nil, unauthorized("not signed in")
	}
	var u models.User
	var expires string
	err := s.repo.QueryRow(`SELECT u.id, u.username, u.email, u.password_hash, u.role, u.active, u.created_at, s.expires_at
		FROM sessions s JOIN users u ON u.id = s.user_id WHERE s.token = ?`, token).
		Scan(&u.ID, &u.Username, &u.Email, &u.PasswordHash, &u.Role, &u.Active, &u.CreatedAt, &expires)
	if err != nil {
		return nil, unauthorized("session is not valid")
	}
	if t, perr := time.Parse(time.RFC3339, expires); perr == nil && t.Before(time.Now().UTC()) {
		_, _ = s.repo.Delete("sessions", map[string]any{"token": token})
		return nil, unauthorized("session has expired")
	}
	return &u, nil
}

// DeleteSession signs a user out (AGENT-01-FA-03).
func (s *Service) DeleteSession(token string) error {
	if token == "" {
		return nil
	}
	_, err := s.repo.Delete("sessions", map[string]any{"token": token})
	return err
}

func (s *Service) purgeExpiredSessions() {
	_, _ = s.repo.Exec("DELETE FROM sessions WHERE expires_at < ?", s.now())
}

func id2str(id int64) string {
	return strconv.FormatInt(id, 10)
}

package auth

import (
	"context"
	"crypto/rand"
	"crypto/subtle"
	"database/sql"
	"encoding/hex"
	"errors"
	"net/http"
	"strings"
	"sync"
	"time"

	"golang.org/x/crypto/bcrypt"

	"github.com/anomaly/p09/internal/models"
)

type Service struct {
	db         *sql.DB
	cookieName string
	ttl        time.Duration
}

func NewService(db *sql.DB, cookieName string, ttl time.Duration) *Service {
	return &Service{db: db, cookieName: cookieName, ttl: ttl}
}

var (
	ErrInvalidCredentials = errors.New("invalid credentials")
	ErrUserExists          = errors.New("user already exists")
	ErrWeakPassword        = errors.New("password too short")
	ErrSessionMissing      = errors.New("session missing")
	ErrSessionExpired      = errors.New("session expired")
)

type RegisterInput struct {
	Username string
	Email    string
	FullName string
	Password string
	Role     string
}

func (s *Service) Register(ctx context.Context, in RegisterInput) (*models.User, error) {
	in.Username = strings.TrimSpace(in.Username)
	in.Email = strings.ToLower(strings.TrimSpace(in.Email))
	in.FullName = strings.TrimSpace(in.FullName)
	if in.Username == "" || in.Email == "" || in.FullName == "" {
		return nil, errors.New("username, email, and full name are required")
	}
	if len(in.Password) < 8 {
		return nil, ErrWeakPassword
	}
	role := in.Role
	if role == "" {
		role = models.RoleDeveloper
	}
	if !validRole(role) {
		return nil, errors.New("invalid role")
	}
	hash, err := bcrypt.GenerateFromPassword([]byte(in.Password), bcrypt.DefaultCost)
	if err != nil {
		return nil, err
	}
	res, err := s.db.ExecContext(ctx, `INSERT INTO users(username, email, full_name, password_hash, role) VALUES (?,?,?,?,?)`,
		in.Username, in.Email, in.FullName, string(hash), role)
	if err != nil {
		if strings.Contains(err.Error(), "UNIQUE") {
			return nil, ErrUserExists
		}
		return nil, err
	}
	id, _ := res.LastInsertId()
	u, err := s.GetByID(ctx, id)
	if err != nil {
		return nil, err
	}
	return u, nil
}

func (s *Service) Authenticate(ctx context.Context, usernameOrEmail, password string) (*models.User, error) {
	key := strings.ToLower(strings.TrimSpace(usernameOrEmail))
	row := s.db.QueryRowContext(ctx, `SELECT id, username, email, full_name, password_hash, role, status, bio, created_at, updated_at FROM users WHERE lower(username)=? OR lower(email)=? LIMIT 1`, key, key)
	u := &models.User{}
	var created, updated string
	if err := row.Scan(&u.ID, &u.Username, &u.Email, &u.FullName, &u.PasswordHash, &u.Role, &u.Status, &u.Bio, &created, &updated); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, ErrInvalidCredentials
		}
		return nil, err
	}
	if u.Status != "active" {
		return nil, ErrInvalidCredentials
	}
	if err := bcrypt.CompareHashAndPassword([]byte(u.PasswordHash), []byte(password)); err != nil {
		return nil, ErrInvalidCredentials
	}
	u.CreatedAt, _ = time.Parse("2006-01-02 15:04:05", created)
	u.UpdatedAt, _ = time.Parse("2006-01-02 15:04:05", updated)
	return u, nil
}

func (s *Service) GetByID(ctx context.Context, id int64) (*models.User, error) {
	row := s.db.QueryRowContext(ctx, `SELECT id, username, email, full_name, password_hash, role, status, bio, created_at, updated_at FROM users WHERE id=?`, id)
	u := &models.User{}
	var created, updated string
	if err := row.Scan(&u.ID, &u.Username, &u.Email, &u.FullName, &u.PasswordHash, &u.Role, &u.Status, &u.Bio, &created, &updated); err != nil {
		return nil, err
	}
	u.CreatedAt, _ = time.Parse("2006-01-02 15:04:05", created)
	u.UpdatedAt, _ = time.Parse("2006-01-02 15:04:05", updated)
	return u, nil
}

func (s *Service) GetByUsername(ctx context.Context, username string) (*models.User, error) {
	row := s.db.QueryRowContext(ctx, `SELECT id, username, email, full_name, password_hash, role, status, bio, created_at, updated_at FROM users WHERE username=?`, username)
	u := &models.User{}
	var created, updated string
	if err := row.Scan(&u.ID, &u.Username, &u.Email, &u.FullName, &u.PasswordHash, &u.Role, &u.Status, &u.Bio, &created, &updated); err != nil {
		return nil, err
	}
	u.CreatedAt, _ = time.Parse("2006-01-02 15:04:05", created)
	u.UpdatedAt, _ = time.Parse("2006-01-02 15:04:05", updated)
	return u, nil
}

func (s *Service) UpdateProfile(ctx context.Context, id int64, fullName, bio string) error {
	_, err := s.db.ExecContext(ctx, `UPDATE users SET full_name=?, bio=?, updated_at=datetime('now') WHERE id=?`, fullName, bio, id)
	return err
}

func (s *Service) ListUsers(ctx context.Context) ([]*models.User, error) {
	rows, err := s.db.QueryContext(ctx, `SELECT id, username, email, full_name, '', role, status, bio, created_at, updated_at FROM users ORDER BY username`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []*models.User
	for rows.Next() {
		u := &models.User{}
		var created, updated string
		if err := rows.Scan(&u.ID, &u.Username, &u.Email, &u.FullName, &u.PasswordHash, &u.Role, &u.Status, &u.Bio, &created, &updated); err != nil {
			return nil, err
		}
		out = append(out, u)
	}
	return out, rows.Err()
}

func (s *Service) SetRole(ctx context.Context, id int64, role string) error {
	if !validRole(role) {
		return errors.New("invalid role")
	}
	_, err := s.db.ExecContext(ctx, `UPDATE users SET role=?, updated_at=datetime('now') WHERE id=?`, role, id)
	return err
}

func (s *Service) SetStatus(ctx context.Context, id int64, status string) error {
	if status != "active" && status != "suspended" {
		return errors.New("invalid status")
	}
	_, err := s.db.ExecContext(ctx, `UPDATE users SET status=?, updated_at=datetime('now') WHERE id=?`, status, id)
	return err
}

func validRole(role string) bool {
	switch role {
	case models.RoleAdmin, models.RoleMaintainer, models.RoleDeveloper, models.RoleReporter, models.RoleMember:
		return true
	}
	return false
}

func newToken(n int) (string, error) {
	b := make([]byte, n)
	if _, err := rand.Read(b); err != nil {
		return "", err
	}
	return hex.EncodeToString(b), nil
}

func (s *Service) CreateSession(ctx context.Context, userID int64, ip, ua string) (*models.Session, error) {
	id, err := newToken(24)
	if err != nil {
		return nil, err
	}
	csrf, err := newToken(16)
	if err != nil {
		return nil, err
	}
	expires := time.Now().Add(s.ttl).UTC().Format("2006-01-02 15:04:05")
	_, err = s.db.ExecContext(ctx, `INSERT INTO sessions(id, user_id, csrf_token, expires_at, ip, user_agent) VALUES (?,?,?,?,?,?)`,
		id, userID, csrf, expires, ip, ua)
	if err != nil {
		return nil, err
	}
	return &models.Session{ID: id, UserID: userID, CSRFToken: csrf, ExpiresAt: time.Now().Add(s.ttl), IP: ip, UA: ua}, nil
}

func (s *Service) LookupSession(ctx context.Context, id string) (*models.Session, *models.User, error) {
	row := s.db.QueryRowContext(ctx, `SELECT id, user_id, csrf_token, created_at, expires_at, ip, user_agent FROM sessions WHERE id=?`, id)
	sess := &models.Session{}
	var created, expires string
	if err := row.Scan(&sess.ID, &sess.UserID, &sess.CSRFToken, &created, &expires, &sess.IP, &sess.UA); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, nil, ErrSessionMissing
		}
		return nil, nil, err
	}
	exp, err := time.Parse("2006-01-02 15:04:05", expires)
	if err != nil {
		return nil, nil, err
	}
	if time.Now().After(exp) {
		_ = s.DeleteSession(ctx, id)
		return nil, nil, ErrSessionExpired
	}
	sess.CreatedAt, _ = time.Parse("2006-01-02 15:04:05", created)
	sess.ExpiresAt = exp
	u, err := s.GetByID(ctx, sess.UserID)
	if err != nil {
		return nil, nil, err
	}
	if u.Status != "active" {
		_ = s.DeleteSession(ctx, id)
		return nil, nil, ErrSessionExpired
	}
	return sess, u, nil
}

func (s *Service) DeleteSession(ctx context.Context, id string) error {
	_, err := s.db.ExecContext(ctx, `DELETE FROM sessions WHERE id=?`, id)
	return err
}

func (s *Service) RotateCSRF(ctx context.Context, id, newToken string) error {
	_, err := s.db.ExecContext(ctx, `UPDATE sessions SET csrf_token=? WHERE id=?`, newToken, id)
	return err
}

func (s *Service) CookieName() string { return s.cookieName }

func (s *Service) WriteCookie(w http.ResponseWriter, sess *models.Session, secure bool) {
	http.SetCookie(w, &http.Cookie{
		Name:     s.cookieName,
		Value:    sess.ID,
		Path:     "/",
		HttpOnly: true,
		Secure:   secure,
		SameSite: http.SameSiteLaxMode,
		Expires:  sess.ExpiresAt,
	})
}

func (s *Service) ClearCookie(w http.ResponseWriter) {
	http.SetCookie(w, &http.Cookie{
		Name:     s.cookieName,
		Value:    "",
		Path:     "/",
		HttpOnly: true,
		Expires:  time.Unix(0, 0),
		MaxAge:   -1,
	})
}

type ctxKey int

const (
	ctxUserKey ctxKey = iota
	ctxSessionKey
	ctxCSRFToken
)

func WithUser(ctx context.Context, u *models.User, sess *models.Session) context.Context {
	ctx = context.WithValue(ctx, ctxUserKey, u)
	ctx = context.WithValue(ctx, ctxSessionKey, sess)
	if sess != nil {
		ctx = context.WithValue(ctx, ctxCSRFToken, sess.CSRFToken)
	}
	return ctx
}

func UserFromContext(ctx context.Context) (*models.User, bool) {
	u, ok := ctx.Value(ctxUserKey).(*models.User)
	return u, ok
}

func SessionFromContext(ctx context.Context) (*models.Session, bool) {
	s, ok := ctx.Value(ctxSessionKey).(*models.Session)
	return s, ok
}

func CSRFToken(ctx context.Context) string {
	if v, ok := ctx.Value(ctxCSRFToken).(string); ok {
		return v
	}
	return ""
}

func CompareCSRF(provided, stored string) bool {
	if provided == "" || stored == "" {
		return false
	}
	return subtle.ConstantTimeCompare([]byte(provided), []byte(stored)) == 1
}

var nonceMu sync.Mutex

func NewCSRF() string {
	nonceMu.Lock()
	defer nonceMu.Unlock()
	t, _ := newToken(16)
	return t
}
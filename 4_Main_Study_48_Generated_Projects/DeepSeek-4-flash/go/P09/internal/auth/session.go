// Package auth implements server-side sessions keyed by an HTTP-only cookie
// with persistent session records in SQLite, plus password hashing.
package auth

import (
	"crypto/rand"
	"encoding/hex"
	"net/http"
	"time"

	"golang.org/x/crypto/bcrypt"

	"issuetracker/internal/models"
	"issuetracker/internal/store"
)

// SessionCookieName is the HTTP-only cookie carrying the session token.
const SessionCookieName = "issuetracker_session"

// Manager creates and validates sessions.
type Manager struct {
	Store *store.Store
	TTL   time.Duration
}

// NewManager builds a session manager with the configured lifetime.
func NewManager(s *store.Store, ttl time.Duration) *Manager {
	return &Manager{Store: s, TTL: ttl}
}

// CreateSession issues a new token, persists it, and sets the cookie.
func (m *Manager) CreateSession(w http.ResponseWriter, userID int64) error {
	token, err := newToken()
	if err != nil {
		return err
	}
	if err := m.Store.CreateSession(userID, token, m.TTL); err != nil {
		return err
	}
	http.SetCookie(w, &http.Cookie{
		Name:     SessionCookieName,
		Value:    token,
		Path:     "/",
		HttpOnly: true,
		SameSite: http.SameSiteLaxMode,
		MaxAge:   int(m.TTL.Seconds()),
	})
	return nil
}

// CurrentUser resolves the session cookie to a user, or returns nil.
func (m *Manager) CurrentUser(r *http.Request) *models.User {
	c, err := r.Cookie(SessionCookieName)
	if err != nil {
		return nil
	}
	u, err := m.Store.SessionUser(c.Value)
	if err != nil {
		return nil
	}
	return u
}

// DestroySession deletes the persisted session and clears the cookie.
func (m *Manager) DestroySession(w http.ResponseWriter, r *http.Request) {
	if c, err := r.Cookie(SessionCookieName); err == nil {
		_ = m.Store.DeleteSession(c.Value)
	}
	http.SetCookie(w, &http.Cookie{
		Name:     SessionCookieName,
		Value:    "",
		Path:     "/",
		HttpOnly: true,
		MaxAge:   -1,
	})
}

// HashPassword returns a bcrypt hash of the password.
func HashPassword(password string) (string, error) {
	b, err := bcrypt.GenerateFromPassword([]byte(password), bcrypt.DefaultCost)
	return string(b), err
}

// CheckPassword reports whether the password matches the hash.
func CheckPassword(hash, password string) bool {
	return bcrypt.CompareHashAndPassword([]byte(hash), []byte(password)) == nil
}

func newToken() (string, error) {
	b := make([]byte, 32)
	if _, err := rand.Read(b); err != nil {
		return "", err
	}
	return hex.EncodeToString(b), nil
}

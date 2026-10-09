package auth

import (
	"context"
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"crypto/subtle"
	"database/sql"
	"encoding/base64"
	"encoding/hex"
	"errors"
	"fmt"
	"strings"
	"time"

	"golang.org/x/crypto/bcrypt"
)

type Service struct {
	db        *sql.DB
	secret    []byte
	lifetime  time.Duration
}

func NewService(db *sql.DB, secret string, lifetime time.Duration) *Service {
	return &Service{db: db, secret: []byte(secret), lifetime: lifetime}
}

func HashPassword(plain string) (string, error) {
	b, err := bcrypt.GenerateFromPassword([]byte(plain), bcrypt.DefaultCost)
	if err != nil {
		return "", err
	}
	return string(b), nil
}

func CheckPassword(hash, plain string) bool {
	return bcrypt.CompareHashAndPassword([]byte(hash), []byte(plain)) == nil
}

func RandomToken(n int) (string, error) {
	b := make([]byte, n)
	if _, err := rand.Read(b); err != nil {
		return "", err
	}
	return base64.RawURLEncoding.EncodeToString(b), nil
}

func NewSessionID() (string, error) {
	b := make([]byte, 32)
	if _, err := rand.Read(b); err != nil {
		return "", err
	}
	return base64.RawURLEncoding.EncodeToString(b), nil
}

func (s *Service) CreateSession(ctx context.Context, userID string) (string, time.Time, error) {
	id, err := NewSessionID()
	if err != nil {
		return "", time.Time{}, err
	}
	sig := s.sign(id)
	token := id + "." + sig
	expires := time.Now().Add(s.lifetime)
	if _, err := s.db.ExecContext(ctx,
		`INSERT INTO sessions (id, user_id, expires_at) VALUES (?, ?, ?)`,
		id, userID, expires,
	); err != nil {
		return "", time.Time{}, err
	}
	return token, expires, nil
}

func (s *Service) DestroyByToken(ctx context.Context, token string) error {
	id, _, err := s.split(token)
	if err != nil {
		return err
	}
	_, err = s.db.ExecContext(ctx, `DELETE FROM sessions WHERE id = ?`, id)
	return err
}

func (s *Service) Lookup(ctx context.Context, token string) (string, error) {
	id, sig, err := s.split(token)
	if err != nil {
		return "", err
	}
	if !s.verify(id, sig) {
		return "", errors.New("invalid signature")
	}
	var userID string
	var expiresAt time.Time
	err = s.db.QueryRowContext(ctx,
		`SELECT user_id, expires_at FROM sessions WHERE id = ?`, id,
	).Scan(&userID, &expiresAt)
	if err == sql.ErrNoRows {
		return "", errors.New("session not found")
	}
	if err != nil {
		return "", err
	}
	if time.Now().After(expiresAt) {
		_, _ = s.db.ExecContext(ctx, `DELETE FROM sessions WHERE id = ?`, id)
		return "", errors.New("session expired")
	}
	return userID, nil
}

func (s *Service) Cleanup(ctx context.Context) error {
	_, err := s.db.ExecContext(ctx, `DELETE FROM sessions WHERE expires_at < ?`, time.Now())
	return err
}

func (s *Service) sign(id string) string {
	mac := hmac.New(sha256.New, s.secret)
	mac.Write([]byte(id))
	return base64.RawURLEncoding.EncodeToString(mac.Sum(nil))
}

func (s *Service) verify(id, sig string) bool {
	expected := s.sign(id)
	if len(expected) != len(sig) {
		return false
	}
	return subtle.ConstantTimeCompare([]byte(expected), []byte(sig)) == 1
}

func (s *Service) split(token string) (string, string, error) {
	parts := strings.SplitN(token, ".", 2)
	if len(parts) != 2 {
		return "", "", errors.New("malformed token")
	}
	return parts[0], parts[1], nil
}

// Deterministic cipher for secret values (used by Secrets manager).
func (s *Service) Encrypt(plain string) (string, error) {
	key := deriveKey(s.secret)
	plainBytes := []byte(plain)
	cipher := make([]byte, len(plainBytes))
	for i, b := range plainBytes {
		cipher[i] = b ^ key[i%len(key)]
	}
	return base64.RawURLEncoding.EncodeToString(cipher), nil
}

func (s *Service) Decrypt(cipher string) (string, error) {
	key := deriveKey(s.secret)
	raw, err := base64.RawURLEncoding.DecodeString(cipher)
	if err != nil {
		return "", err
	}
	out := make([]byte, len(raw))
	for i, b := range raw {
		out[i] = b ^ key[i%len(key)]
	}
	return string(out), nil
}

func MaskSecret(plain string) string {
	if len(plain) <= 4 {
		return strings.Repeat("*", len(plain))
	}
	return plain[:2] + strings.Repeat("*", len(plain)-4) + plain[len(plain)-2:]
}

func deriveKey(secret []byte) []byte {
	h := sha256.Sum256(secret)
	out := make([]byte, hex.EncodedLen(len(h)))
	hex.Encode(out, h[:])
	return out
}

var _ = fmt.Sprintf
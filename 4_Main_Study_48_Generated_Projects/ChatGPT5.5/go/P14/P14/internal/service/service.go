package service

import (
	"crypto/rand"
	"database/sql"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"regexp"
	"strings"
	"time"

	"p14-agentic-platform/internal/config"
	"p14-agentic-platform/internal/models"
	"p14-agentic-platform/internal/repo"
)

// AppError is a user-visible, deterministic error. It never leaks stack traces.
type AppError struct {
	Status  int
	Message string
}

func (e *AppError) Error() string { return e.Message }

func badRequest(msg string) error   { return &AppError{Status: http.StatusBadRequest, Message: msg} }
func unauthorized(msg string) error { return &AppError{Status: http.StatusUnauthorized, Message: msg} }
func forbidden(msg string) error    { return &AppError{Status: http.StatusForbidden, Message: msg} }
func notFound(msg string) error     { return &AppError{Status: http.StatusNotFound, Message: msg} }
func conflict(msg string) error     { return &AppError{Status: http.StatusConflict, Message: msg} }

// Exported constructors for handlers and middleware.
func BadRequest(msg string) error   { return badRequest(msg) }
func Unauthorized(msg string) error { return unauthorized(msg) }
func Forbidden(msg string) error    { return forbidden(msg) }
func NotFound(msg string) error     { return notFound(msg) }
func Conflict(msg string) error     { return conflict(msg) }

// StrField reads a string field from an input map (exported for handlers).
func StrField(m map[string]any, key string) string { return str(m, key) }

// Service bundles business logic on top of the repository layer.
type Service struct {
	cfg  *config.Config
	repo *repo.Repo
}

func New(cfg *config.Config, r *repo.Repo) *Service {
	return &Service{cfg: cfg, repo: r}
}

func (s *Service) now() string { return time.Now().UTC().Format(time.RFC3339) }

// UserByID loads a user by id.
func (s *Service) UserByID(id int64) (*models.User, error) {
	row := s.repo.QueryRow("SELECT id, username, email, password_hash, role, active, created_at FROM users WHERE id = ?", id)
	u := &models.User{}
	if err := row.Scan(&u.ID, &u.Username, &u.Email, &u.PasswordHash, &u.Role, &u.Active, &u.CreatedAt); err != nil {
		return nil, notFound("user not found")
	}
	return u, nil
}

// WorkflowByID loads a workflow and verifies ownership.
func (s *Service) WorkflowByID(userID, id int64) (*models.Workflow, error) {
	row := s.repo.QueryRow(`SELECT id, user_id, name, description, trigger_type, steps_json, conditions_json, enabled, created_at, updated_at
		FROM workflows WHERE id = ? AND user_id = ?`, id, userID)
	w := &models.Workflow{}
	err := row.Scan(&w.ID, &w.UserID, &w.Name, &w.Description, &w.TriggerType, &w.StepsJSON, &w.ConditionsJSON, &w.Enabled, &w.CreatedAt, &w.UpdatedAt)
	if err != nil {
		if err == sql.ErrNoRows {
			return nil, notFound("workflow not found")
		}
		return nil, err
	}
	return w, nil
}

// GetSetting returns a governance setting value or fallback.
func (s *Service) GetSetting(key, fallback string) string {
	var v string
	err := s.repo.QueryRow("SELECT setting_value FROM governance_settings WHERE setting_key = ?", key).Scan(&v)
	if err != nil {
		return fallback
	}
	return v
}

// DisabledActions returns the list of resources whose create/update is disabled.
func (s *Service) DisabledActions() []string {
	raw := s.GetSetting("disabled_actions", "[]")
	var out []string
	if err := json.Unmarshal([]byte(raw), &out); err != nil {
		return nil
	}
	return out
}

func (s *Service) isDisabled(name string) bool {
	for _, d := range s.DisabledActions() {
		if d == name {
			return true
		}
	}
	return false
}

// Quota returns the current count for a resource owned by the user.
func (s *Service) Quota(sch *repo.Schema, userID int64) (int, error) {
	if sch.Owner == "" {
		return 0, nil
	}
	return s.repo.Count(sch, map[string]any{sch.Owner: userID})
}

func (s *Service) quotaLimit(key string, def int) int {
	raw := s.GetSetting(key, fmt.Sprint(def))
	var n int
	if _, err := fmt.Sscanf(raw, "%d", &n); err != nil {
		return def
	}
	return n
}

// Audit records a privileged/observable action.
func (s *Service) Audit(actorID int64, action, entityType, entityID, detail string) {
	_, _ = s.repo.Exec("INSERT INTO audit_events(actor_id, action, entity_type, entity_id, detail, created_at) VALUES(?,?,?,?,?,?)",
		actorID, action, entityType, entityID, detail, s.now())
}

var emailRe = regexp.MustCompile(`^[^@\s]+@[^@\s]+\.[^@\s]+$`)

func validEmail(v string) bool { return emailRe.MatchString(v) }

// randomHex generates a cryptographically random hex string of n bytes.
func randomHex(n int) string {
	b := make([]byte, n)
	if _, err := rand.Read(b); err != nil {
		panic(err)
	}
	return hex.EncodeToString(b)
}

// mask hides a secret value, keeping only a short prefix.
func mask(v string) string {
	if v == "" {
		return ""
	}
	if len(v) <= 4 {
		return "••••"
	}
	return v[:4] + "••••••••••"
}

// str reads a string field from an input map.
func str(m map[string]any, key string) string {
	v, _ := m[key].(string)
	return strings.TrimSpace(v)
}

func int64Field(m map[string]any, key string) (int64, bool) {
	switch v := m[key].(type) {
	case float64:
		return int64(v), true
	case int64:
		return v, true
	case json.Number:
		n, err := v.Int64()
		return n, err == nil
	}
	return 0, false
}

func boolField(m map[string]any, key string, def bool) bool {
	switch v := m[key].(type) {
	case bool:
		return v
	case string:
		if v == "true" || v == "1" || v == "on" {
			return true
		}
		if v == "false" || v == "0" {
			return false
		}
	}
	return def
}

func hasKey(m map[string]any, key string) bool {
	_, ok := m[key]
	return ok
}

// validJSON verifies a string is valid JSON.
func validJSON(v string) error {
	var x any
	if err := json.Unmarshal([]byte(v), &x); err != nil {
		return badRequest("field must contain valid JSON")
	}
	return nil
}

// parseURLParams converts a raw query into filter values.
func parseURLParams(q url.Values, sch *repo.Schema) map[string]any {
	where := map[string]any{}
	colTypes := map[string]string{}
	for _, c := range sch.Cols {
		colTypes[c.Name] = c.Typ
	}
	for k, vs := range q {
		if k == "q" || len(vs) == 0 {
			continue
		}
		typ, ok := colTypes[k]
		if !ok {
			continue
		}
		v := vs[0]
		switch typ {
		case "bool":
			where[k] = v == "true" || v == "1" || v == "on"
		case "int":
			var n int64
			if _, err := fmt.Sscanf(v, "%d", &n); err == nil {
				where[k] = n
			}
		default:
			where[k] = v
		}
	}
	return where
}

// colType returns the scan type of a schema column.
func colType(sch *repo.Schema, name string) string {
	for _, c := range sch.Cols {
		if c.Name == name {
			return c.Typ
		}
	}
	return "text"
}

// parseValue converts a single query value to the column's native type.
func parseValue(sch *repo.Schema, key, v string) any {
	switch colType(sch, key) {
	case "bool":
		return v == "true" || v == "1" || v == "on"
	case "int":
		var n int64
		if _, err := fmt.Sscanf(v, "%d", &n); err == nil {
			return n
		}
		return v
	default:
		return v
	}
}

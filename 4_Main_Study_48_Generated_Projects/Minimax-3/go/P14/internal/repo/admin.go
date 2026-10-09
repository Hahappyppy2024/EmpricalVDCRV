package repo

import (
	"context"
	"database/sql"
	"strings"

	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/google/uuid"
)

type Admin struct{ DB *sql.DB }

func (r *Admin) ListSettings(ctx context.Context) ([]models.AdminSetting, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, key, value, COALESCE(updated_by,''), updated_at FROM admin_governance ORDER BY key`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.AdminSetting
	for rows.Next() {
		s := models.AdminSetting{}
		if err := rows.Scan(&s.ID, &s.Key, &s.Value, &s.UpdatedBy, &s.UpdatedAt); err != nil {
			return nil, err
		}
		out = append(out, s)
	}
	return out, rows.Err()
}

func (r *Admin) SetSetting(ctx context.Context, key, value, actorID string) error {
	_, err := r.DB.ExecContext(ctx,
		`UPDATE admin_governance SET value = ?, updated_by = ?, updated_at = datetime('now') WHERE key = ?`,
		value, actorID, key)
	return err
}

func (r *Admin) Setting(ctx context.Context, key string) (string, error) {
	row := r.DB.QueryRowContext(ctx, `SELECT value FROM admin_governance WHERE key = ?`, key)
	var v string
	if err := row.Scan(&v); err != nil {
		if err == sql.ErrNoRows {
			return "", nil
		}
		return "", err
	}
	return v, nil
}

func (r *Admin) Boolean(ctx context.Context, key string, def bool) bool {
	v, err := r.Setting(ctx, key)
	if err != nil || v == "" {
		return def
	}
	return strings.EqualFold(v, "true") || v == "1"
}

type Audit struct{ DB *sql.DB }

func (r *Audit) Append(ctx context.Context, actorID, action, target, detail string) error {
	_, err := r.DB.ExecContext(ctx,
		`INSERT INTO audit_events (id, actor_id, action, target, detail) VALUES (?, ?, ?, ?, ?)`,
		uuid.NewString(), nullable(actorID), action, target, detail)
	return err
}

func (r *Audit) Recent(ctx context.Context, limit int) ([]models.AuditEvent, error) {
	if limit <= 0 || limit > 500 {
		limit = 100
	}
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, COALESCE(actor_id,''), action, target, detail, created_at FROM audit_events ORDER BY created_at DESC LIMIT ?`, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.AuditEvent
	for rows.Next() {
		a := models.AuditEvent{}
		if err := rows.Scan(&a.ID, &a.ActorID, &a.Action, &a.Target, &a.Detail, &a.CreatedAt); err != nil {
			return nil, err
		}
		out = append(out, a)
	}
	return out, rows.Err()
}
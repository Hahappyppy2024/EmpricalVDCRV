package repo

import (
	"context"
	"database/sql"

	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/google/uuid"
)

type AccessLog struct{ DB *sql.DB }

func (r *AccessLog) Append(ctx context.Context, userID, action, status, detail string) error {
	_, err := r.DB.ExecContext(ctx,
		`INSERT INTO account_access (id, user_id, action, status, detail) VALUES (?, ?, ?, ?, ?)`,
		uuid.NewString(), userID, action, status, detail)
	return err
}

func (r *AccessLog) Recent(ctx context.Context, userID string, limit int) ([]models.AccountAccess, error) {
	if limit <= 0 || limit > 200 {
		limit = 50
	}
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, user_id, action, status, detail, created_at FROM account_access WHERE user_id = ? ORDER BY created_at DESC LIMIT ?`,
		userID, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.AccountAccess
	for rows.Next() {
		a := models.AccountAccess{}
		if err := rows.Scan(&a.ID, &a.UserID, &a.Action, &a.Status, &a.Detail, &a.CreatedAt); err != nil {
			return nil, err
		}
		out = append(out, a)
	}
	return out, rows.Err()
}

var _ = sql.ErrNoRows
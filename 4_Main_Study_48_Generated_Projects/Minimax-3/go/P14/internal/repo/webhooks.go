package repo

import (
	"context"
	"database/sql"
	"errors"
	"time"

	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/google/uuid"
)

type Webhooks struct{ DB *sql.DB }

func (r *Webhooks) Create(ctx context.Context, userID, wfID, token, desc string) (*models.Webhook, error) {
	id := uuid.NewString()
	if _, err := r.DB.ExecContext(ctx,
		`INSERT INTO webhook_triggers (id, user_id, workflow_id, token, description) VALUES (?, ?, ?, ?, ?)`,
		id, userID, wfID, token, desc); err != nil {
		return nil, err
	}
	return r.ByID(ctx, id)
}

func (r *Webhooks) ByID(ctx context.Context, id string) (*models.Webhook, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, user_id, workflow_id, token, description, revoked, created_at FROM webhook_triggers WHERE id = ?`, id)
	return scanWebhook(row)
}

func (r *Webhooks) ByToken(ctx context.Context, token string) (*models.Webhook, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, user_id, workflow_id, token, description, revoked, created_at FROM webhook_triggers WHERE token = ?`, token)
	return scanWebhook(row)
}

func (r *Webhooks) ListByUser(ctx context.Context, userID string) ([]models.Webhook, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, user_id, workflow_id, token, description, revoked, created_at FROM webhook_triggers WHERE user_id = ? ORDER BY created_at DESC`, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Webhook
	for rows.Next() {
		w, err := scanWebhookRows(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, *w)
	}
	return out, rows.Err()
}

func (r *Webhooks) Revoke(ctx context.Context, id, userID string) error {
	res, err := r.DB.ExecContext(ctx,
		`UPDATE webhook_triggers SET revoked = 1 WHERE id = ? AND user_id = ?`, id, userID)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("not found")
	}
	return nil
}

func scanWebhook(row *sql.Row) (*models.Webhook, error) {
	w := &models.Webhook{}
	var revoked int
	if err := row.Scan(&w.ID, &w.UserID, &w.WorkflowID, &w.Token, &w.Description, &revoked, &w.CreatedAt); err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, err
	}
	w.Revoked = revoked == 1
	return w, nil
}

func scanWebhookRows(rows *sql.Rows) (*models.Webhook, error) {
	w := &models.Webhook{}
	var revoked int
	if err := rows.Scan(&w.ID, &w.UserID, &w.WorkflowID, &w.Token, &w.Description, &revoked, &w.CreatedAt); err != nil {
		return nil, err
	}
	w.Revoked = revoked == 1
	return w, nil
}

var _ = time.Now
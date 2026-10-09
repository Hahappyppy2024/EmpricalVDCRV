package repo

import (
	"context"
	"database/sql"
	"time"

	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/google/uuid"
)

type HTTPActions struct{ DB *sql.DB }

func (r *HTTPActions) Record(ctx context.Context, userID, wfID, url, method, payload string, status int, resp, errMsg string) (*models.ExternalHTTPCall, error) {
	id := uuid.NewString()
	if _, err := r.DB.ExecContext(ctx,
		`INSERT INTO external_http_action (id, user_id, workflow_id, url, method, payload, status_code, response, error) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		id, userID, nullable(wfID), url, method, payload, status, resp, errMsg); err != nil {
		return nil, err
	}
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, user_id, COALESCE(workflow_id,''), url, method, payload, status_code, response, error, created_at FROM external_http_action WHERE id = ?`, id)
	c := &models.ExternalHTTPCall{}
	if err := row.Scan(&c.ID, &c.UserID, &c.WorkflowID, &c.URL, &c.Method, &c.Payload, &c.StatusCode, &c.Response, &c.Error, &c.CreatedAt); err != nil {
		return nil, err
	}
	return c, nil
}

func (r *HTTPActions) ListByUser(ctx context.Context, userID string) ([]models.ExternalHTTPCall, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, user_id, COALESCE(workflow_id,''), url, method, payload, status_code, response, error, created_at FROM external_http_action WHERE user_id = ? ORDER BY created_at DESC`, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.ExternalHTTPCall
	for rows.Next() {
		c := models.ExternalHTTPCall{}
		if err := rows.Scan(&c.ID, &c.UserID, &c.WorkflowID, &c.URL, &c.Method, &c.Payload, &c.StatusCode, &c.Response, &c.Error, &c.CreatedAt); err != nil {
			return nil, err
		}
		out = append(out, c)
	}
	return out, rows.Err()
}

func (r *HTTPActions) ListAll(ctx context.Context) ([]models.ExternalHTTPCall, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, user_id, COALESCE(workflow_id,''), url, method, payload, status_code, response, error, created_at FROM external_http_action ORDER BY created_at DESC`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.ExternalHTTPCall
	for rows.Next() {
		c := models.ExternalHTTPCall{}
		if err := rows.Scan(&c.ID, &c.UserID, &c.WorkflowID, &c.URL, &c.Method, &c.Payload, &c.StatusCode, &c.Response, &c.Error, &c.CreatedAt); err != nil {
			return nil, err
		}
		out = append(out, c)
	}
	return out, rows.Err()
}

var _ = time.Now
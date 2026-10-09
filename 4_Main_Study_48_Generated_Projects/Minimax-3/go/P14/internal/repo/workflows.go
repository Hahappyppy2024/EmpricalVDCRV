package repo

import (
	"context"
	"database/sql"
	"time"

	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/google/uuid"
)

type Workflows struct{ DB *sql.DB }

func (r *Workflows) Create(ctx context.Context, userID, name, desc, trigger, cron string, steps []byte) (*models.Workflow, error) {
	id := uuid.NewString()
	if _, err := r.DB.ExecContext(ctx,
		`INSERT INTO workflow_creation (id, user_id, name, description, trigger_kind, cron_expr, steps_json, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')`,
		id, userID, name, desc, trigger, cron, string(steps)); err != nil {
		return nil, err
	}
	return r.ByID(ctx, id)
}

func (r *Workflows) ByID(ctx context.Context, id string) (*models.Workflow, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, user_id, name, description, trigger_kind, cron_expr, steps_json, status, created_at, updated_at FROM workflow_creation WHERE id = ?`, id)
	w := &models.Workflow{}
	var stepsJSON string
	if err := row.Scan(&w.ID, &w.UserID, &w.Name, &w.Description, &w.TriggerKind, &w.CronExpr, &stepsJSON, &w.Status, &w.CreatedAt, &w.UpdatedAt); err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, err
	}
	w.Steps = decodeSteps(stepsJSON)
	return w, nil
}

func (r *Workflows) ByName(ctx context.Context, userID, name string) (*models.Workflow, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, user_id, name, description, trigger_kind, cron_expr, steps_json, status, created_at, updated_at FROM workflow_creation WHERE user_id = ? AND name = ?`, userID, name)
	w := &models.Workflow{}
	var stepsJSON string
	if err := row.Scan(&w.ID, &w.UserID, &w.Name, &w.Description, &w.TriggerKind, &w.CronExpr, &stepsJSON, &w.Status, &w.CreatedAt, &w.UpdatedAt); err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, err
	}
	w.Steps = decodeSteps(stepsJSON)
	return w, nil
}

func (r *Workflows) ListByUser(ctx context.Context, userID string) ([]models.Workflow, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, user_id, name, description, trigger_kind, cron_expr, steps_json, status, created_at, updated_at FROM workflow_creation WHERE user_id = ? ORDER BY updated_at DESC`, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Workflow
	for rows.Next() {
		w := models.Workflow{}
		var stepsJSON string
		if err := rows.Scan(&w.ID, &w.UserID, &w.Name, &w.Description, &w.TriggerKind, &w.CronExpr, &stepsJSON, &w.Status, &w.CreatedAt, &w.UpdatedAt); err != nil {
			return nil, err
		}
		w.Steps = decodeSteps(stepsJSON)
		out = append(out, w)
	}
	return out, rows.Err()
}

func (r *Workflows) ListAll(ctx context.Context) ([]models.Workflow, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, user_id, name, description, trigger_kind, cron_expr, steps_json, status, created_at, updated_at FROM workflow_creation ORDER BY updated_at DESC`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Workflow
	for rows.Next() {
		w := models.Workflow{}
		var stepsJSON string
		if err := rows.Scan(&w.ID, &w.UserID, &w.Name, &w.Description, &w.TriggerKind, &w.CronExpr, &stepsJSON, &w.Status, &w.CreatedAt, &w.UpdatedAt); err != nil {
			return nil, err
		}
		w.Steps = decodeSteps(stepsJSON)
		out = append(out, w)
	}
	return out, rows.Err()
}

func (r *Workflows) Update(ctx context.Context, id, name, desc, trigger, cron, status string, steps []byte) error {
	now := time.Now().UTC().Format(time.RFC3339)
	_, err := r.DB.ExecContext(ctx,
		`UPDATE workflow_creation SET name = ?, description = ?, trigger_kind = ?, cron_expr = ?, steps_json = ?, status = ?, updated_at = ? WHERE id = ?`,
		name, desc, trigger, cron, string(steps), status, now, id)
	return err
}

func (r *Workflows) AttachTool(ctx context.Context, wfID, toolID string) error {
	_, err := r.DB.ExecContext(ctx,
		`INSERT OR IGNORE INTO workflow_tools (workflow_id, tool_id) VALUES (?, ?)`, wfID, toolID)
	return err
}

func (r *Workflows) Tools(ctx context.Context, wfID string) ([]models.Tool, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT t.id, t.name, t.kind, t.description, t.default_json, t.enabled, t.created_at FROM tool_catalog t JOIN workflow_tools wt ON wt.tool_id = t.id WHERE wt.workflow_id = ? ORDER BY t.name`, wfID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Tool
	for rows.Next() {
		t := models.Tool{}
		var defJSON string
		var enabled int
		if err := rows.Scan(&t.ID, &t.Name, &t.Kind, &t.Description, &defJSON, &enabled, &t.CreatedAt); err != nil {
			return nil, err
		}
		t.Enabled = enabled == 1
		t.Default = decodeMap(defJSON)
		out = append(out, t)
	}
	return out, rows.Err()
}
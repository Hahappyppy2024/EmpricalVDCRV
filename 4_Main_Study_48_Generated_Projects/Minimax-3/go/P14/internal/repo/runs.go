package repo

import (
	"context"
	"database/sql"
	"errors"
	"time"

	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/google/uuid"
)

type Runs struct{ DB *sql.DB }

func (r *Runs) Create(ctx context.Context, userID, wfID, trigger string) (*models.Run, error) {
	id := uuid.NewString()
	if _, err := r.DB.ExecContext(ctx,
		`INSERT INTO task_execution (id, user_id, workflow_id, trigger, status, detail) VALUES (?, ?, ?, ?, 'queued', 'queued')`,
		id, userID, wfID, trigger); err != nil {
		return nil, err
	}
	return r.ByID(ctx, id)
}

func (r *Runs) ByID(ctx context.Context, id string) (*models.Run, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, user_id, workflow_id, trigger, status, started_at, finished_at, detail, created_at FROM task_execution WHERE id = ?`, id)
	return scanRun(row)
}

func (r *Runs) ListByUser(ctx context.Context, userID string) ([]models.Run, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, user_id, workflow_id, trigger, status, started_at, finished_at, detail, created_at FROM task_execution WHERE user_id = ? ORDER BY created_at DESC`, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Run
	for rows.Next() {
		run, err := scanRunRows(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, *run)
	}
	return out, rows.Err()
}

func (r *Runs) ListAll(ctx context.Context) ([]models.Run, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, user_id, workflow_id, trigger, status, started_at, finished_at, detail, created_at FROM task_execution ORDER BY created_at DESC`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Run
	for rows.Next() {
		run, err := scanRunRows(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, *run)
	}
	return out, rows.Err()
}

func (r *Runs) SetStatus(ctx context.Context, id, status, detail string) error {
	now := time.Now().UTC().Format(time.RFC3339)
	switch status {
	case "running":
		_, err := r.DB.ExecContext(ctx, `UPDATE task_execution SET status = ?, started_at = ?, detail = ? WHERE id = ?`, status, now, detail, id)
		return err
	case "success", "failed", "canceled":
		_, err := r.DB.ExecContext(ctx, `UPDATE task_execution SET status = ?, finished_at = ?, detail = ? WHERE id = ?`, status, now, detail, id)
		return err
	default:
		return errors.New("invalid status")
	}
}

func (r *Runs) AddStep(ctx context.Context, runID string, seq int, name, toolID string) (string, error) {
	id := uuid.NewString()
	_, err := r.DB.ExecContext(ctx,
		`INSERT INTO task_steps (id, run_id, seq, name, tool_id, status) VALUES (?, ?, ?, ?, ?, 'pending')`,
		id, runID, seq, name, toolID)
	return id, err
}

func (r *Runs) StepStart(ctx context.Context, stepID string) error {
	now := time.Now().UTC().Format(time.RFC3339)
	_, err := r.DB.ExecContext(ctx, `UPDATE task_steps SET status = 'running', started_at = ? WHERE id = ?`, now, stepID)
	return err
}

func (r *Runs) StepFinish(ctx context.Context, stepID, status, output, errMsg string) error {
	now := time.Now().UTC().Format(time.RFC3339)
	_, err := r.DB.ExecContext(ctx, `UPDATE task_steps SET status = ?, output = ?, error = ?, finished_at = ? WHERE id = ?`, status, output, errMsg, now, stepID)
	return err
}

func (r *Runs) Steps(ctx context.Context, runID string) ([]models.RunStep, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, run_id, seq, name, COALESCE(tool_id, ''), status, started_at, finished_at, output, error FROM task_steps WHERE run_id = ? ORDER BY seq`, runID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.RunStep
	for rows.Next() {
		s := models.RunStep{}
		if err := rows.Scan(&s.ID, &s.RunID, &s.Seq, &s.Name, &s.ToolID, &s.Status, &s.StartedAt, &s.FinishedAt, &s.Output, &s.Error); err != nil {
			return nil, err
		}
		out = append(out, s)
	}
	return out, rows.Err()
}

func scanRun(row *sql.Row) (*models.Run, error) {
	run := &models.Run{}
	if err := row.Scan(&run.ID, &run.UserID, &run.WorkflowID, &run.Trigger, &run.Status, &run.StartedAt, &run.FinishedAt, &run.Detail, &run.CreatedAt); err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, err
	}
	return run, nil
}

func scanRunRows(rows *sql.Rows) (*models.Run, error) {
	run := &models.Run{}
	if err := rows.Scan(&run.ID, &run.UserID, &run.WorkflowID, &run.Trigger, &run.Status, &run.StartedAt, &run.FinishedAt, &run.Detail, &run.CreatedAt); err != nil {
		return nil, err
	}
	return run, nil
}
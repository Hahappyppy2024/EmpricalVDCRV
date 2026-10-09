package repo

import (
	"context"
	"database/sql"
	"strings"
	"time"

	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/google/uuid"
)

type Schedules struct{ DB *sql.DB }

func (r *Schedules) Create(ctx context.Context, userID, wfID, cron string, nextRun time.Time, enabled bool) (*models.Schedule, error) {
	id := uuid.NewString()
	if _, err := r.DB.ExecContext(ctx,
		`INSERT INTO scheduled_runs (id, user_id, workflow_id, cron_expr, next_run_at, enabled) VALUES (?, ?, ?, ?, ?, ?)`,
		id, userID, wfID, cron, nextRun.UTC().Format(time.RFC3339), boolToInt(enabled)); err != nil {
		return nil, err
	}
	return r.ByID(ctx, id)
}

func (r *Schedules) ByID(ctx context.Context, id string) (*models.Schedule, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, user_id, workflow_id, cron_expr, next_run_at, enabled, created_at FROM scheduled_runs WHERE id = ?`, id)
	return scanSchedule(row)
}

func (r *Schedules) ListByUser(ctx context.Context, userID string) ([]models.Schedule, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, user_id, workflow_id, cron_expr, next_run_at, enabled, created_at FROM scheduled_runs WHERE user_id = ? ORDER BY next_run_at`, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Schedule
	for rows.Next() {
		s, err := scanScheduleRows(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, *s)
	}
	return out, rows.Err()
}

func (r *Schedules) ListAll(ctx context.Context) ([]models.Schedule, error) {
	rows, err := r.DB.QueryContext(ctx,
		`SELECT id, user_id, workflow_id, cron_expr, next_run_at, enabled, created_at FROM scheduled_runs ORDER BY next_run_at`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Schedule
	for rows.Next() {
		s, err := scanScheduleRows(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, *s)
	}
	return out, rows.Err()
}

func (r *Schedules) Update(ctx context.Context, id, cron string, nextRun time.Time, enabled bool) error {
	_, err := r.DB.ExecContext(ctx,
		`UPDATE scheduled_runs SET cron_expr = ?, next_run_at = ?, enabled = ? WHERE id = ?`,
		cron, nextRun.UTC().Format(time.RFC3339), boolToInt(enabled), id)
	return err
}

func (r *Schedules) Delete(ctx context.Context, id string) error {
	_, err := r.DB.ExecContext(ctx, `DELETE FROM scheduled_runs WHERE id = ?`, id)
	return err
}

func (r *Schedules) CronValid(expr string) bool {
	fields := strings.Fields(expr)
	return len(fields) == 5
}

func scanSchedule(row *sql.Row) (*models.Schedule, error) {
	s := &models.Schedule{}
	var enabled int
	if err := row.Scan(&s.ID, &s.UserID, &s.WorkflowID, &s.CronExpr, &s.NextRunAt, &enabled, &s.CreatedAt); err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, err
	}
	s.Enabled = enabled == 1
	return s, nil
}

func scanScheduleRows(rows *sql.Rows) (*models.Schedule, error) {
	s := &models.Schedule{}
	var enabled int
	if err := rows.Scan(&s.ID, &s.UserID, &s.WorkflowID, &s.CronExpr, &s.NextRunAt, &enabled, &s.CreatedAt); err != nil {
		return nil, err
	}
	s.Enabled = enabled == 1
	return s, nil
}
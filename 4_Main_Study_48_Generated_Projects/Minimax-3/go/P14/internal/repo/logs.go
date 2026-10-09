package repo

import (
	"context"
	"database/sql"
	"strings"

	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/google/uuid"
)

type RunLogs struct{ DB *sql.DB }

func (r *RunLogs) Append(ctx context.Context, userID, runID, level, message string) (*models.RunLog, error) {
	id := uuid.NewString()
	if _, err := r.DB.ExecContext(ctx,
		`INSERT INTO run_logs_and_replay (id, user_id, run_id, level, message) VALUES (?, ?, ?, ?, ?)`,
		id, userID, runID, level, message); err != nil {
		return nil, err
	}
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, user_id, run_id, level, message, created_at FROM run_logs_and_replay WHERE id = ?`, id)
	l := &models.RunLog{}
	if err := row.Scan(&l.ID, &l.UserID, &l.RunID, &l.Level, &l.Message, &l.CreatedAt); err != nil {
		return nil, err
	}
	return l, nil
}

func (r *RunLogs) ByRun(ctx context.Context, runID, userID string, isAdmin bool) ([]models.RunLog, error) {
	q := `SELECT id, user_id, run_id, level, message, created_at FROM run_logs_and_replay WHERE run_id = ?`
	args := []any{runID}
	if !isAdmin {
		q += " AND user_id = ?"
		args = append(args, userID)
	}
	q += " ORDER BY created_at"
	rows, err := r.DB.QueryContext(ctx, q, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.RunLog
	for rows.Next() {
		l := models.RunLog{}
		if err := rows.Scan(&l.ID, &l.UserID, &l.RunID, &l.Level, &l.Message, &l.CreatedAt); err != nil {
			return nil, err
		}
		out = append(out, l)
	}
	return out, rows.Err()
}

func (r *RunLogs) Filter(ctx context.Context, userID, runID, level, q string, isAdmin bool) ([]models.RunLog, error) {
	clauses := []string{}
	args := []any{}
	if runID != "" {
		clauses = append(clauses, "run_id = ?")
		args = append(args, runID)
	}
	if level != "" {
		clauses = append(clauses, "level = ?")
		args = append(args, level)
	}
	if q != "" {
		clauses = append(clauses, "LOWER(message) LIKE ?")
		args = append(args, "%"+strings.ToLower(q)+"%")
	}
	if !isAdmin {
		clauses = append(clauses, "user_id = ?")
		args = append(args, userID)
	}
	stmt := `SELECT id, user_id, run_id, level, message, created_at FROM run_logs_and_replay`
	if len(clauses) > 0 {
		stmt += " WHERE " + strings.Join(clauses, " AND ")
	}
	stmt += " ORDER BY created_at DESC LIMIT 200"
	rows, err := r.DB.QueryContext(ctx, stmt, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.RunLog
	for rows.Next() {
		l := models.RunLog{}
		if err := rows.Scan(&l.ID, &l.UserID, &l.RunID, &l.Level, &l.Message, &l.CreatedAt); err != nil {
			return nil, err
		}
		out = append(out, l)
	}
	return out, rows.Err()
}
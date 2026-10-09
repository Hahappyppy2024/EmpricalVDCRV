package repo

import (
	"context"
	"database/sql"
	"errors"
	"strings"

	"github.com/anomalyco/p14-workflow-automation/internal/models"
	"github.com/google/uuid"
)

type Templates struct{ DB *sql.DB }

func (r *Templates) Create(ctx context.Context, userID, wfID, name, desc, bodyJSON, visibility string) (*models.Template, error) {
	id := uuid.NewString()
	if _, err := r.DB.ExecContext(ctx,
		`INSERT INTO sharing_and_templates (id, user_id, workflow_id, name, description, body_json, visibility) VALUES (?, ?, ?, ?, ?, ?, ?)`,
		id, userID, nullable(wfID), name, desc, bodyJSON, visibility); err != nil {
		return nil, err
	}
	return r.ByID(ctx, id)
}

func (r *Templates) ByID(ctx context.Context, id string) (*models.Template, error) {
	row := r.DB.QueryRowContext(ctx,
		`SELECT id, user_id, COALESCE(workflow_id,''), name, description, body_json, visibility, created_at FROM sharing_and_templates WHERE id = ?`, id)
	return scanTemplate(row)
}

func (r *Templates) List(ctx context.Context, userID string, isAdmin bool, visibilityFilter string) ([]models.Template, error) {
	clauses := []string{}
	args := []any{}
	if !isAdmin {
		clauses = append(clauses, "(user_id = ? OR visibility IN ('shared','public'))")
		args = append(args, userID)
	}
	if visibilityFilter != "" {
		clauses = append(clauses, "visibility = ?")
		args = append(args, visibilityFilter)
	}
	q := `SELECT id, user_id, COALESCE(workflow_id,''), name, description, body_json, visibility, created_at FROM sharing_and_templates`
	if len(clauses) > 0 {
		q += " WHERE " + strings.Join(clauses, " AND ")
	}
	q += " ORDER BY created_at DESC"
	rows, err := r.DB.QueryContext(ctx, q, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []models.Template
	for rows.Next() {
		t, err := scanTemplateRows(rows)
		if err != nil {
			return nil, err
		}
		out = append(out, *t)
	}
	return out, rows.Err()
}

func (r *Templates) UpdateVisibility(ctx context.Context, id, userID, visibility string, isAdmin bool) error {
	if visibility != "private" && visibility != "shared" && visibility != "public" {
		return errors.New("invalid visibility")
	}
	q := `UPDATE sharing_and_templates SET visibility = ? WHERE id = ?`
	args := []any{visibility, id}
	if !isAdmin {
		q += " AND user_id = ?"
		args = append(args, userID)
	}
	res, err := r.DB.ExecContext(ctx, q, args...)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("not found")
	}
	return nil
}

func (r *Templates) Delete(ctx context.Context, id, userID string, isAdmin bool) error {
	q := `DELETE FROM sharing_and_templates WHERE id = ?`
	args := []any{id}
	if !isAdmin {
		q += " AND user_id = ?"
		args = append(args, userID)
	}
	res, err := r.DB.ExecContext(ctx, q, args...)
	if err != nil {
		return err
	}
	n, _ := res.RowsAffected()
	if n == 0 {
		return errors.New("not found")
	}
	return nil
}

func scanTemplate(row *sql.Row) (*models.Template, error) {
	t := &models.Template{}
	var bodyJSON string
	if err := row.Scan(&t.ID, &t.UserID, &t.WorkflowID, &t.Name, &t.Description, &bodyJSON, &t.Visibility, &t.CreatedAt); err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, err
	}
	t.Body = decodeAnyMap(bodyJSON)
	return t, nil
}

func scanTemplateRows(rows *sql.Rows) (*models.Template, error) {
	t := &models.Template{}
	var bodyJSON string
	if err := rows.Scan(&t.ID, &t.UserID, &t.WorkflowID, &t.Name, &t.Description, &bodyJSON, &t.Visibility, &t.CreatedAt); err != nil {
		return nil, err
	}
	t.Body = decodeAnyMap(bodyJSON)
	return t, nil
}